<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\NamingClasses;

use Hihaho\PhpstanRules\Traits\HasUrlTip;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Reflection\ReflectionProvider;

/**
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Class_>
 */
abstract class SuffixableRule implements Rule
{
    use HasUrlTip;

    private ReflectionProvider $reflectionProvider;

    abstract public function baseClass(): string;
    
    abstract public function suffix(): string;
    
    abstract public function name(): string;

    abstract public function docs(): string;

    public function __construct(
        ReflectionProvider $reflectionProvider,
    ) {
        $this->reflectionProvider = $reflectionProvider;
    }
    
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Class_) {
            return [];
        }

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

        if (Str::endsWith($node->name->toString(), $this->suffix())) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                "{$this->name()} {$node->namespacedName} must be named with a `{$this->suffix()}` suffix, such as {$node->name}{$this->suffix()}."
            )
                ->tip($this->docsTip($this->docs()))
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
