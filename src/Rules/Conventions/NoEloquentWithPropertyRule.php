<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Conventions;

use Illuminate\Database\Eloquent\Model;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Flags the declaration of Eloquent's `$with` property on a Model. A non-empty
 * `$with` eager-loads its relations on *every* query for that model — globally,
 * invisibly, and regardless of whether the caller needs them. Load relations
 * explicitly at the call site with `with()` / `loadMissing()` instead.
 *
 * Registered directly — property declarations are rare, so a dedicated dispatch
 * is cheap (same reasoning as the constructor flag rule).
 *
 * @implements Rule<Property>
 */
final class NoEloquentWithPropertyRule implements Rule
{
    #[Override]
    public function getNodeType(): string
    {
        return Property::class;
    }

    /**
     * @param  Property  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->declaresWithProperty($node)) {
            return [];
        }

        if (! $this->isEagerLoadingDefault($node)) {
            return [];
        }

        if (! $this->isInModelSubclass($scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Declaring Eloquent\'s $with property globally eager-loads its relations on every query. Load relations explicitly at the call site instead.',
            )
                ->identifier('hihaho.conventions.noEloquentWithProperty')
                ->tip('Use $query->with([...]) or $model->loadMissing([...]) where the relations are actually needed.')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function declaresWithProperty(Property $node): bool
    {
        foreach ($node->props as $prop) {
            if ($prop->name->toString() === 'with') {
                return true;
            }
        }

        return false;
    }

    /**
     * Skip an explicit empty default (`$with = []`), which eager-loads nothing
     * and merely restates Eloquent's own default. Anything else is flagged.
     */
    private function isEagerLoadingDefault(Property $node): bool
    {
        foreach ($node->props as $prop) {
            if ($prop->name->toString() !== 'with') {
                continue;
            }

            $default = $prop->default;

            if ($default instanceof Array_ && $default->items === []) {
                return false;
            }
        }

        return true;
    }

    private function isInModelSubclass(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection) {
            return false;
        }

        return (new ObjectType(Model::class))
            ->isSuperTypeOf(new ObjectType($classReflection->getName()))
            ->yes();
    }
}
