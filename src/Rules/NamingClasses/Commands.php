<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\NamingClasses;

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
 * @see https://guidelines.hihaho.com/laravel.html#commands
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Class_>
 */
class Commands implements Rule
{
    private ReflectionProvider $reflectionProvider;

    public function __construct(
        ReflectionProvider $reflectionProvider
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

        if ($node->extends->toString() !== Command::class && ! $this->parentExtendsCommand($parent)) {
            return [];
        }

        if (Str::endsWith($node->name->toString(), 'Command')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                "Command {$node->namespacedName} must be named with a `Command` suffix, such as {$node->name}Command."
            )->build(),
        ];
    }
    
    private function parentExtendsCommand(ClassReflection $reflection): bool
    {
        foreach ($reflection->getParents() as $parent) {
            if ($parent->getName() === Command::class) {
                return true;
            }
        }

        return false;
    }
}
