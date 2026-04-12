<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Illuminate\Support\Facades\Facade;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionClass;
use ReflectionException;

/**
 * @implements Rule<StaticCall>
 */
final readonly class OnlyAllowFacadeAliasInBlade implements Rule
{
    #[Override]
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param  StaticCall  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->class instanceof Name) {
            return [];
        }

        // A facade alias is not relative to the current namespace, neither is it `self`, `parent` or `static`.
        if ($node->class->isRelative() || $node->class->isSpecialClassName()) {
            return [];
        }

        // The class consists of multiple parts, so it's (likely) not a facade alias.
        if (count($node->class->getParts()) > 1) {
            return [];
        }

        // Ignore calls in Blade files.
        if (str_ends_with($scope->getFileDescription(), '.blade.php')) {
            return [];
        }

        try {
            // Runtime reflection is required: facade aliases are registered
            // lazily by Laravel's AliasLoader (an SPL autoloader). PHPStan's
            // ReflectionProvider does not invoke runtime autoloaders, so a
            // static-discovery path would silently miss every real-world
            // facade alias. The try/catch handles non-existent short names.
            // @phpstan-ignore phpstanApi.runtimeReflection, argument.type
            $reflectionClass = new ReflectionClass($node->class->toCodeString());
        } catch (ReflectionException) {
            return [];
        }

        if ($reflectionClass->isSubclassOf(Facade::class)) {
            return [
                RuleErrorBuilder::message(
                    "Disallowed usage of `{$node->class->toString()}` facade alias, use `{$reflectionClass->getName()}`. A facade alias can only be used in Blade."
                )
                    ->identifier('hihaho.generic.onlyAllowFacadeAliasInBlade')
                    ->build(),
            ];
        }

        return [];
    }
}
