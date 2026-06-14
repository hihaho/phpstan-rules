<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use Illuminate\Support\Facades\Facade;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionClass;
use ReflectionException;

/**
 * Shared detection for facade-alias usage outside Blade — used by both the
 * standalone OnlyAllowFacadeAliasInBlade and the registered CombinedStaticCallRule.
 */
trait DetectsFacadeAlias
{
    private function facadeAliasError(Name $class, Scope $scope): ?IdentifierRuleError
    {
        // A facade alias is not relative to the current namespace, neither is it `self`, `parent` or `static`.
        if ($class->isRelative() || $class->isSpecialClassName()) {
            return null;
        }

        // The class consists of multiple parts, so it's (likely) not a facade alias.
        if (str_contains($class->name, '\\')) {
            return null;
        }

        // Ignore calls in Blade files.
        if (str_ends_with($scope->getFileDescription(), '.blade.php')) {
            return null;
        }

        $className = $class->name;

        /** @var array<string, ReflectionClass<object>|null> $cache */
        static $cache = [];

        if (! array_key_exists($className, $cache)) {
            try {
                // Runtime reflection is required: facade aliases are registered
                // lazily by Laravel's AliasLoader (an SPL autoloader). PHPStan's
                // ReflectionProvider does not invoke runtime autoloaders, so a
                // static-discovery path would silently miss every real-world
                // facade alias. The try/catch handles non-existent short names.
                // @phpstan-ignore phpstanApi.runtimeReflection, argument.type
                $cache[$className] = new ReflectionClass($className);
            } catch (ReflectionException) {
                $cache[$className] = null;
            }
        }

        $reflectionClass = $cache[$className];

        if (! $reflectionClass instanceof ReflectionClass) {
            return null;
        }

        if ($reflectionClass->isSubclassOf(Facade::class)) {
            return RuleErrorBuilder::message(
                "Disallowed usage of `{$class->name}` facade alias, use `{$reflectionClass->getName()}`. A facade alias can only be used in Blade."
            )
                ->identifier('hihaho.generic.onlyAllowFacadeAliasInBlade')
                ->build();
        }

        return null;
    }
}
