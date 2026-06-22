<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\ParameterClosureTypes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Override;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ClosureType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MethodParameterClosureTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Infers the related-model builder type for the closure passed to Eloquent's relationship-existence
 * query methods (whereHas, has, doesntHave, …) when the relation is given by name.
 *
 * Laravel 12 already types these callbacks as `Closure(Builder<TRelatedModel>): mixed` and binds
 * `TRelatedModel` from the `Relation<TRelatedModel, …>|string $relation` parameter. But when the
 * relation is passed as a string ('posts'), PHPStan cannot bind `TRelatedModel` from a string, so
 * it falls back to the template bound — base `Model` — and the closure receives `Builder<Model>`.
 * Under `checkModelProperties` that breaks every `->where(Model::SOME_COLUMN)` inside the closure:
 * the column is checked against base `Model`, which has none, so it errors on valid columns.
 *
 * This extension resolves the concrete related model from the relation name via reflection and types
 * the closure parameter as `Builder<TRelatedModel>`. PHPStan intersects this with any explicit hint
 * the user wrote, so a bare `Builder $query` (an unparameterised `Builder`, a supertype of every
 * `Builder<…>`) collapses to the related builder — no per-site annotation needed, arrow functions
 * preserved. Dotted nested relations ('posts.comments' → Builder<Comment>) are walked segment by
 * segment.
 *
 * Soundness: the extension only narrows when it can prove the related model from a single constant
 * relation name and a resolvable relation method whose return type carries a concrete related model.
 * In every other case it returns null and the default behaviour stands, so a genuinely wrong column
 * on the correct related model still fails — this widens nothing.
 */
final readonly class RelationExistenceClosureBuilderParameterExtension implements MethodParameterClosureTypeExtension
{
    /**
     * Relationship-existence methods on the Eloquent builder whose closure receives a builder scoped
     * to the related model. Compared lowercased — PHP method names are case-insensitive.
     */
    private const array RELATION_METHODS = [
        'has',
        'orhas',
        'doesnthave',
        'ordoesnthave',
        'wherehas',
        'orwherehas',
        'wheredoesnthave',
        'orwheredoesnthave',
    ];

    public function __construct(private ReflectionProvider $reflectionProvider) {}

    #[Override]
    public function isMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        if ($parameter->getName() !== 'callback') {
            return false;
        }

        if (! in_array(strtolower($methodReflection->getName()), self::RELATION_METHODS, true)) {
            return false;
        }

        return $methodReflection->getDeclaringClass()->is(Builder::class);
    }

    #[Override]
    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, ParameterReflection $parameter, Scope $scope): ?Type
    {
        $relationArgument = $this->findRelationArgument($methodCall);

        if (! $relationArgument instanceof Expr) {
            return null;
        }

        $relationNames = $scope->getType($relationArgument)->getConstantStrings();

        if (count($relationNames) !== 1) {
            return null;
        }

        $modelClass = $this->resolveReceiverModelClass($scope->getType($methodCall->var));

        if ($modelClass === null) {
            return null;
        }

        foreach (explode('.', $relationNames[0]->getValue()) as $relationName) {
            $modelClass = $this->resolveRelatedModelClass($modelClass, $relationName, $scope);

            if ($modelClass === null) {
                return null;
            }
        }

        return new ClosureType(
            [new ClosureParameter('query', $this->resolveBuilderType($modelClass, $scope))],
            new MixedType(),
        );
    }

    /**
     * Resolves the builder type the related model's queries actually use. A plain model yields
     * `Builder<TRelatedModel>`; a model with a custom builder (Laravel's `HasBuilder` trait or an
     * overridden `newEloquentBuilder()`) yields that custom builder, so relation-specific builder
     * methods inside the closure keep resolving. Derived from the model's own `newQuery()` return
     * type rather than hard-coded so both cases stay correct.
     */
    private function resolveBuilderType(string $modelClass, Scope $scope): Type
    {
        $modelType = new ObjectType($modelClass);

        if ($modelType->hasMethod('newQuery')->yes()) {
            $builderType = ParametersAcceptorSelector::selectFromArgs(
                $scope,
                [],
                $modelType->getMethod('newQuery', $scope)->getVariants(),
            )->getReturnType();

            if ((new ObjectType(Builder::class))->isSuperTypeOf($builderType)->yes()) {
                return $builderType;
            }
        }

        return new GenericObjectType(Builder::class, [$modelType]);
    }

    /**
     * Locates the `$relation` argument regardless of call style: by name when passed as a named
     * argument (which may appear out of order, e.g. `whereHas(callback: …, relation: 'bars')`),
     * otherwise the first positional argument — `$relation` is the first parameter of every method.
     */
    private function findRelationArgument(MethodCall $methodCall): ?Expr
    {
        $firstPositional = null;

        foreach ($methodCall->getArgs() as $arg) {
            if ($arg->name !== null) {
                if ($arg->name->toString() === 'relation') {
                    return $arg->value;
                }

                continue;
            }

            $firstPositional ??= $arg->value;
        }

        return $firstPositional;
    }

    /**
     * Resolves the model the relation methods are scoped to. The receiver is normally a
     * `Builder<TModel>`, but the methods are also reachable on a relation
     * (`$user->posts()->whereHas(…)`), whose builder is scoped to the relation's related model.
     */
    private function resolveReceiverModelClass(Type $receiverType): ?string
    {
        return $this->resolveModelClass($receiverType->getTemplateType(Builder::class, 'TModel'))
            ?? $this->resolveModelClass($receiverType->getTemplateType(Relation::class, 'TRelatedModel'));
    }

    private function resolveRelatedModelClass(string $modelClass, string $relationName, Scope $scope): ?string
    {
        if (! $this->reflectionProvider->hasClass($modelClass)) {
            return null;
        }

        $classReflection = $this->reflectionProvider->getClass($modelClass);

        if (! $classReflection->hasMethod($relationName)) {
            return null;
        }

        $relationType = ParametersAcceptorSelector::selectFromArgs(
            $scope,
            [],
            $classReflection->getMethod($relationName, $scope)->getVariants(),
        )->getReturnType();

        if (! (new ObjectType(Relation::class))->isSuperTypeOf($relationType)->yes()) {
            return null;
        }

        return $this->resolveModelClass(
            $relationType->getTemplateType(Relation::class, 'TRelatedModel'),
        );
    }

    /**
     * Reduces a type to the single concrete Eloquent model class it refers to, or null when it is
     * ambiguous, unknown, or merely the base Model (which carries no useful related-model info).
     */
    private function resolveModelClass(Type $type): ?string
    {
        $classNames = $type->getObjectClassNames();

        if (count($classNames) !== 1) {
            return null;
        }

        $className = $classNames[0];

        if ($className === Model::class) {
            return null;
        }

        if (! $this->reflectionProvider->hasClass($className)) {
            return null;
        }

        if (! $this->reflectionProvider->getClass($className)->is(Model::class)) {
            return null;
        }

        return $className;
    }
}
