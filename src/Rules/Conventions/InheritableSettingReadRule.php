<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Conventions;

use InvalidArgumentException;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

/**
 * A setting that inherits from a parent (a folder, an account) stores null to
 * mean "use the parent's value". Reading the column directly treats that null
 * as the setting's value, so the child silently ignores what it was meant to
 * inherit. The model's resolved getter walks the inheritance chain instead.
 *
 * Only reads are reported: writing null is how a setting goes back to
 * inheriting. Each consuming project configures its own classes and columns;
 * this package ships none.
 *
 * @implements Rule<PropertyFetch>
 * @see InheritableSettingReadRuleTest
 */
final readonly class InheritableSettingReadRule implements Rule
{
    /**
     * Canonically-cased class => property lookup.
     *
     * @var array<string, array<string, true>>
     */
    private array $settings;

    /**
     * @param  array<string, list<string>>  $inheritableSettings  class FQCN => property names that inherit when null
     */
    public function __construct(
        array $inheritableSettings,
        private ReflectionProvider $reflectionProvider,
    ) {
        $this->settings = $this->resolveSettings($inheritableSettings);
    }

    #[Override]
    public function getNodeType(): string
    {
        return PropertyFetch::class;
    }

    /**
     * @param  PropertyFetch  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->settings === []) {
            return [];
        }

        if (! $node->name instanceof Identifier || $scope->isInExpressionAssign($node)) {
            return [];
        }

        $class = $this->configuredClassOf($node->var, $node->name->name, $scope);

        if ($class === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message("Reading {$this->shortName($class)}::\${$node->name->name} directly ignores inheritance: null means inherit.")
                ->identifier('hihaho.conventions.inheritableSettingRead')
                ->tip('Use the resolved getter, which falls back to the inherited value. If the raw column is what you need, add @phpstan-ignore hihaho.conventions.inheritableSettingRead (reason).')
                ->build(),
        ];
    }

    private function configuredClassOf(Expr $receiver, string $property, Scope $scope): ?string
    {
        $type = TypeCombinator::removeNull($scope->getType($receiver));

        foreach ($this->settings as $class => $properties) {
            if (isset($properties[$property]) && new ObjectType($class)->isSuperTypeOf($type)->yes()) {
                return $class;
            }
        }

        return null;
    }

    /**
     * A typo in a project's phpstan.neon must not turn this rule into a silent
     * no-op, so an unknown class name aborts the analysis instead.
     *
     * @param  array<string, list<string>>  $inheritableSettings
     * @return array<string, array<string, true>>
     */
    private function resolveSettings(array $inheritableSettings): array
    {
        $settings = [];

        foreach ($inheritableSettings as $class => $properties) {
            if (! $this->reflectionProvider->hasClass($class)) {
                throw new InvalidArgumentException(
                    "Parameter inheritableSettings: '{$class}' is not an existing class.",
                );
            }

            $settings[$this->reflectionProvider->getClass($class)->getName()] = array_fill_keys($properties, true);
        }

        return $settings;
    }

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }
}
