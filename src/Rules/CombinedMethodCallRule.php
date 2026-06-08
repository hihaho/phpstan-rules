<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Rules\Debug\BaseNoDebugRule;
use Illuminate\Http\Request;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Combined rule that handles all MethodCall checks in a single PHPStan dispatch,
 * eliminating the overhead of dispatching to two separate rules for every method call.
 *
 * Merges: ChainedNoDebugInNamespaceRule + NoUnsafeRequestDataRule
 *
 * @extends BaseNoDebugRule<MethodCall>
 */
final readonly class CombinedMethodCallRule extends BaseNoDebugRule
{
    private const string DEBUG_MESSAGE = 'No chained debug statements should be present in the %s namespace.';

    /** @var array<string, true> */
    private array $unsafeMethodsLookup;

    /** @var array<string, true> */
    private array $quickRejectLookup;

    private ObjectType $requestType;

    /**
     * @param  list<string>  $unsafeMethods
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        array $unsafeMethods,
        private array $namespaces,
        private array $excludeNamespaces,
    ) {
        $this->unsafeMethodsLookup = array_fill_keys(array_map(strtolower(...), $unsafeMethods), true);
        $this->requestType = new ObjectType(Request::class);
        $this->quickRejectLookup = $this->unsafeMethodsLookup + ['dump' => true, 'dd' => true, 'ddd' => true, 'ray' => true];
    }

    #[Override]
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param  MethodCall  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->name;

        if (! isset($this->quickRejectLookup[strtolower($methodName)])) {
            return [];
        }

        $errors = [];

        $debugError = $this->checkDebugMethodCall($node, $methodName, $scope);
        if ($debugError !== null) {
            $errors[] = $debugError;
        }

        $requestError = $this->checkUnsafeRequestData($node, $methodName, $scope);
        if ($requestError !== null) {
            $errors[] = $requestError;
        }

        return $errors;
    }

    private function checkDebugMethodCall(MethodCall $node, string $methodName, Scope $scope): ?IdentifierRuleError
    {
        if (! $this->isDebugMethod($methodName)) {
            return null;
        }

        $namespace = $this->matchDebugNamespace($scope);

        if ($namespace === null) {
            return null;
        }

        if (! $this->isDebugHelperMethodCall($node, $scope, $methodName)) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(self::DEBUG_MESSAGE, $namespace))
            ->identifier("hihaho.debug.noChainedDebugIn{$namespace}")
            ->build();
    }

    private function checkUnsafeRequestData(MethodCall $node, string $methodName, Scope $scope): ?IdentifierRuleError
    {
        if (! isset($this->unsafeMethodsLookup[strtolower($methodName)])) {
            return null;
        }

        if (! $this->isInRequestNamespace($scope)) {
            return null;
        }

        if ($this->scopeClassIsRequest($scope)) {
            return null;
        }

        if (! $this->typeIsRequest($scope->getType($node->var))) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(
            'Reading unvalidated request data via %s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
            $methodName,
        ))
            ->identifier('hihaho.validation.noUnsafeRequestData')
            ->tip('Use $request->validated() or $request->safe() to consume validated data. For Stringable/int/bool accessors, $request->safe()->string(\'key\') mirrors $request->string(\'key\') against validated input.')
            ->build();
    }

    /**
     * A `->dump()` / `->dd()` chain is only a real debug call when the method
     * is declared by a Laravel-framework class or trait. Unrelated user methods
     * that happen to share the name (e.g. a custom `->dump()` on a value object)
     * are not flagged. Unknown receiver types are skipped.
     */
    private function isDebugHelperMethodCall(MethodCall $node, Scope $scope, string $methodName): bool
    {
        $classReflections = $scope->getType($node->var)->getObjectClassReflections();

        if ($classReflections === []) {
            return false;
        }

        foreach ($classReflections as $classReflection) {
            if (! $classReflection->hasMethod($methodName)) {
                continue;
            }

            $declaringClassName = $classReflection->getMethod($methodName, $scope)->getDeclaringClass()->getName();

            if (str_starts_with($declaringClassName, self::LARAVEL_NAMESPACE_PREFIX)) {
                return true;
            }
        }

        return false;
    }

    private function scopeClassIsRequest(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        return $classReflection instanceof ClassReflection && $this->classIsRequest($classReflection->getName());
    }

    private function typeIsRequest(Type $type): bool
    {
        foreach ($type->getObjectClassNames() as $className) {
            if ($this->classIsRequest($className)) {
                return true;
            }
        }

        return false;
    }

    private function classIsRequest(string $className): bool
    {
        /** @var array<string, bool> $cache */
        static $cache = [];

        if (! array_key_exists($className, $cache)) {
            $cache[$className] = $this->requestType->isSuperTypeOf(new ObjectType($className))->yes();
        }

        return $cache[$className];
    }

    private function isInRequestNamespace(Scope $scope): bool
    {
        return $this->namespaceStartsWithAny($scope, $this->namespaces)
            && ! $this->namespaceStartsWithAny($scope, $this->excludeNamespaces);
    }
}
