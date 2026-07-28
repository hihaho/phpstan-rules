<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Conventions;

use InvalidArgumentException;
use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHP cannot make a trait require an interface, so a trait and the contract it
 * is supposed to satisfy drift apart silently: a class picks up the trait's
 * methods without ever appearing in the interface's implementations. Nothing
 * breaks at runtime, but every tool that identifies those classes *by
 * interface* — other rules, dynamic return type extensions, scanners — quietly
 * skips them.
 *
 * Each consuming project configures its own trait => interface pairs; this
 * package ships none.
 *
 * @implements Rule<InClassNode>
 * @see TraitRequiresInterfaceRuleTest
 */
final class TraitRequiresInterfaceRule implements Rule
{
    /**
     * Canonically-cased pairs, so a configured name that differs in case from
     * the declaration (PHP class names are case-insensitive) still matches.
     *
     * @var array<string, string>
     */
    private readonly array $pairs;

    /**
     * @param  array<string, string>  $traitRequiresInterface  trait FQCN => interface FQCN the using class must implement
     */
    public function __construct(
        array $traitRequiresInterface,
        private readonly ReflectionProvider $reflectionProvider,
    ) {
        $this->pairs = $this->resolvePairs($traitRequiresInterface);
    }

    #[Override]
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param  InClassNode  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->pairs === []) {
            return [];
        }

        $classReflection = $node->getClassReflection();

        if (! $this->isCheckableClassLike($classReflection)) {
            return [];
        }

        $usedTraits = $this->collectTraitNames($classReflection);

        $errors = [];

        foreach ($this->pairs as $trait => $interface) {
            if (! in_array($trait, $usedTraits, true)) {
                continue;
            }

            if ($classReflection->implementsInterface($interface)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(
                "{$classReflection->getDisplayName()} uses {$this->shortName($trait)} but does not implement {$this->shortName($interface)}.",
            )
                ->identifier('hihaho.conventions.traitRequiresInterface')
                ->tip("Add {$interface} to the implements clause so the contract can be relied on statically.")
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * A typo in a project's phpstan.neon must not turn this rule into a silent
     * no-op, so unknown or mis-typed class names abort the analysis instead.
     *
     * @param  array<string, string>  $traitRequiresInterface
     * @return array<string, string>
     */
    private function resolvePairs(array $traitRequiresInterface): array
    {
        $pairs = [];

        foreach ($traitRequiresInterface as $trait => $interface) {
            if (! $this->reflectionProvider->hasClass($trait) || ! $this->reflectionProvider->getClass($trait)->isTrait()) {
                throw new InvalidArgumentException(
                    "Parameter traitRequiresInterface: '{$trait}' is not an existing trait.",
                );
            }

            if (! $this->reflectionProvider->hasClass($interface) || ! $this->reflectionProvider->getClass($interface)->isInterface()) {
                throw new InvalidArgumentException(
                    "Parameter traitRequiresInterface: '{$interface}' is not an existing interface.",
                );
            }

            $pairs[$this->reflectionProvider->getClass($trait)->getName()] = $this->reflectionProvider->getClass($interface)->getName();
        }

        return $pairs;
    }

    /**
     * A trait cannot implement an interface — not even one that uses a
     * configured trait itself — and an interface has no implements clause to
     * fix, so only classes and enums are checked.
     */
    private function isCheckableClassLike(ClassReflection $classReflection): bool
    {
        return ! $classReflection->isTrait() && ! $classReflection->isInterface();
    }

    /**
     * Traits reached through another trait or through a parent class count as
     * used: the class gets the methods either way.
     *
     * @return list<string>
     */
    private function collectTraitNames(ClassReflection $classReflection): array
    {
        $names = [];

        foreach ($classReflection->getTraits(true) as $trait) {
            $names[] = $trait->getName();
        }

        return array_values(array_unique($names));
    }

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }
}
