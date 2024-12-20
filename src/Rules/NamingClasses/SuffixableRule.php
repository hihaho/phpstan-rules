<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\NamingClasses;

use Hihaho\PhpstanRules\Traits\HasUrlTip;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Class_>
 */
abstract class SuffixableRule implements Rule
{
    use HasUrlTip;

    abstract public function baseClass(): string;

    abstract public function suffix(): string;

    abstract public function name(): string;

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
        //
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->extends instanceof Node\Name) {
            return [];
        }

        if ($node->isAbstract()) {
            return [];
        }

        $parent = $this->reflectionProvider->getClass($scope->resolveName($node->extends));

        if ($node->extends->toString() !== $this->baseClass() && ! $this->parentExtendsCommand($parent)) {
            return [];
        }

        if (! $node->name instanceof Identifier) {
            return [];
        }

        if (Str::endsWith($node->name->toString(), $this->suffix())) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                "{$this->name()} {$node->namespacedName} must be named with a `{$this->suffix()}` suffix, such as {$node->name}{$this->suffix()}."
            )
                ->tip($this->tip())
                ->identifier('hihaho.naming.classes.' . $this->name())
                ->build(),
        ];
    }

    private function parentExtendsCommand(ClassReflection $reflection): bool
    {
        foreach ($reflection->getParents() as $parent) {
            if ($parent->getName() === $this->baseClass()) {
                return true;
            }
        }

        return false;
    }
}
