<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\ReturnTypes;

use Illuminate\Support\Facades\Route;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\UnionType;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Reads one `Route::model()` / `Route::bind()` call from a provider's boot() into the
 * route-parameter name and the type it binds. Used by RouteBindingReturnTypeExtension.
 *
 * @internal
 */
final readonly class RouteBindingCallReader
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    /**
     * @return array{string, Type}|null
     */
    public function bindingFromCall(StaticCall $call): ?array
    {
        if (! $call->class instanceof Name || $call->class->toString() !== Route::class) {
            return null;
        }

        if (! $call->name instanceof Identifier) {
            return null;
        }

        $args = $call->getArgs();
        $parameterName = $this->parameterName($args[0]->value ?? null);

        if ($parameterName === null) {
            return null;
        }

        $type = match ($call->name->toLowerString()) {
            // A third "missing model" callback can return something other than the model, so a
            // binding that supplies one is skipped rather than narrowed unsoundly.
            'model' => isset($args[2]) ? null : $this->modelBindingType($args[1]->value ?? null),
            'bind' => $this->closureBindingType($args[1]->value ?? null),
            default => null,
        };

        if (! $type instanceof Type) {
            return null;
        }

        return [$parameterName, $type];
    }

    /**
     * The route-parameter name from a binding's first argument: a string literal, or a class
     * constant that resolves to a single string (`RouteParams::LOCALE`). `self::`/`static::` are not
     * resolved — name resolution leaves them as special names with no reflectable class.
     */
    private function parameterName(?Node $argument): ?string
    {
        if ($argument instanceof String_) {
            return $argument->value;
        }

        if (! $argument instanceof ClassConstFetch
            || ! $argument->class instanceof Name
            || ! $argument->name instanceof Identifier) {
            return null;
        }

        $class = $argument->class->toString();

        if (! $this->reflectionProvider->hasClass($class)) {
            return null;
        }

        $classReflection = $this->reflectionProvider->getClass($class);

        if (! $classReflection->hasConstant($argument->name->toString())) {
            return null;
        }

        // The value expression, not the value type — a typed constant (`const string X = 'x'`) types
        // as `string`, so only the expression carries the literal name.
        $valueExpr = $classReflection->getConstant($argument->name->toString())->getValueExpr();

        return $valueExpr instanceof String_ ? $valueExpr->value : null;
    }

    private function modelBindingType(?Node $argument): ?Type
    {
        if (! $argument instanceof ClassConstFetch || ! $argument->class instanceof Name) {
            return null;
        }

        if (! $argument->name instanceof Identifier || $argument->name->toLowerString() !== 'class') {
            return null;
        }

        return new ObjectType($argument->class->toString());
    }

    private function closureBindingType(?Node $argument): ?Type
    {
        if (! $argument instanceof Closure && ! $argument instanceof ArrowFunction) {
            return null;
        }

        $returnType = $argument->getReturnType();

        if ($returnType instanceof NullableType) {
            $returnType = $returnType->type;
        }

        // `T|null` carries the same information as `?T`; take the sole class member, if there is one.
        if ($returnType instanceof UnionType) {
            $names = array_values(array_filter($returnType->types, static fn (Node $type): bool => $type instanceof Name));
            $returnType = count($names) === 1 ? $names[0] : null;
        }

        if (! $returnType instanceof Name) {
            return null;
        }

        return new ObjectType($returnType->toString());
    }
}
