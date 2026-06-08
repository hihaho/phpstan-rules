<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Rules\Debug\BaseNoDebugRule;
use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Illuminate\Http\Request;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Combined rule that handles all MethodCall checks in a single PHPStan dispatch,
 * eliminating the overhead of dispatching to two separate rules for every method call.
 *
 * Merges: ChainedNoDebugInNamespaceRule + NoUnsafeRequestDataRule
 *
 * @implements Rule<MethodCall>
 */
final readonly class CombinedMethodCallRule extends BaseNoDebugRule implements Rule
{
    use ChecksNamespace;

    private const string DEBUG_MESSAGE = 'No chained debug statements should be present in the %s namespace.';

    /** @var array<string, true> */
    private array $unsafeMethodsLookup;

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
        $errors = [];

        // --- ChainedNoDebugInNamespaceRule ---
        if ($this->isDebugMethod($methodName)) {
            if ($this->namespaceStartsWith($scope, 'App')) {
                if ($this->isDebugHelperMethodCall($node, $scope, $methodName)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(self::DEBUG_MESSAGE, 'App'))
                        ->identifier('hihaho.debug.noChainedDebugInApp')
                        ->build();
                }
            } elseif ($this->namespaceStartsWith($scope, 'Tests')) {
                if ($this->isDebugHelperMethodCall($node, $scope, $methodName)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(self::DEBUG_MESSAGE, 'Tests'))
                        ->identifier('hihaho.debug.noChainedDebugInTests')
                        ->build();
                }
            }
        }

        // --- NoUnsafeRequestDataRule ---
        if (isset($this->unsafeMethodsLookup[strtolower($methodName)])
            && $this->isInRequestNamespace($scope)
            && ! $this->scopeClassIsRequest($scope)
            && $this->typeIsRequest($scope->getType($node->var))
        ) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Reading unvalidated request data via %s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
                $methodName,
            ))
                ->identifier('hihaho.validation.noUnsafeRequestData')
                ->tip('Use $request->validated() or $request->safe() to consume validated data. For Stringable/int/bool accessors, $request->safe()->string(\'key\') mirrors $request->string(\'key\') against validated input.')
                ->build();
        }

        return $errors;
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
