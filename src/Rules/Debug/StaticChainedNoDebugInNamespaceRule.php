<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Illuminate\Support\Facades\Facade;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @extends BaseNoDebugRule<StaticCall>
 */
final readonly class StaticChainedNoDebugInNamespaceRule extends BaseNoDebugRule
{
    private const string MESSAGE = 'No statically called debug statements should be present in the %s namespace.';

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
        //
    }

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
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        if (! $this->isDebugMethod($methodName)) {
            return [];
        }

        if (! $this->isLaravelStaticDebugCall($node, $scope, $methodName)) {
            return [];
        }

        if ($this->namespaceStartsWith($scope, 'App')) {
            return [
                RuleErrorBuilder::message(sprintf(self::MESSAGE, 'App'))
                    ->identifier('hihaho.debug.noStaticChainedDebugInApp')
                    ->build(),
            ];
        }

        if ($this->namespaceStartsWith($scope, 'Tests')) {
            return [
                RuleErrorBuilder::message(sprintf(self::MESSAGE, 'Tests'))
                    ->identifier('hihaho.debug.noStaticChainedDebugInTests')
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * A `Class::dump()` / `Class::dd()` is only a real debug call when either:
     *   1. PHPStan can resolve the method and its declaring class lives in the
     *      Laravel namespace (typical for facades that expose `dump`/`dd` via
     *      `@method` annotations, e.g. `Http::dump()`), OR
     *   2. The class is a subclass of `Illuminate\Support\Facades\Facade` —
     *      facades without `@method static ... dump()` still proxy the call
     *      through `Facade::__callStatic` to `Dumpable`/`EnumeratesValues` at
     *      runtime, so `Cache::dump()` is genuinely a debug call even when
     *      PHPStan (without larastan) can't resolve the method statically.
     * Unrelated user classes with their own static `dump`/`dd` method are not
     * flagged.
     */
    private function isLaravelStaticDebugCall(StaticCall $node, Scope $scope, string $methodName): bool
    {
        if (! $node->class instanceof Name) {
            return false;
        }

        $className = $scope->resolveName($node->class);

        if (! $this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        if ($classReflection->hasMethod($methodName)) {
            $declaringClassName = $classReflection->getMethod($methodName, $scope)->getDeclaringClass()->getName();

            if (str_starts_with($declaringClassName, self::LARAVEL_NAMESPACE_PREFIX)) {
                return true;
            }
        }

        return $this->isFacadeSubclass($classReflection);
    }

    private function isFacadeSubclass(ClassReflection $classReflection): bool
    {
        if (! $this->reflectionProvider->hasClass(Facade::class)) {
            return false;
        }

        return $classReflection->isSubclassOfClass($this->reflectionProvider->getClass(Facade::class));
    }
}
