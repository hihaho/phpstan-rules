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

    private ?ClassReflection $facadeReflection;

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
        $this->facadeReflection = $reflectionProvider->hasClass(Facade::class)
            ? $reflectionProvider->getClass(Facade::class)
            : null;
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

        $methodName = $node->name->name;

        if (! $this->isDebugMethod($methodName)) {
            return [];
        }

        $namespace = $this->matchDebugNamespace($scope);

        if ($namespace === null) {
            return [];
        }

        if (! $this->isLaravelStaticDebugCall($node, $scope, $methodName)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(self::MESSAGE, $namespace))
                ->identifier("hihaho.debug.noStaticChainedDebugIn{$namespace}")
                ->build(),
        ];
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
        if (! $this->facadeReflection instanceof ClassReflection) {
            return false;
        }

        return $classReflection->isSubclassOfClass($this->facadeReflection);
    }
}
