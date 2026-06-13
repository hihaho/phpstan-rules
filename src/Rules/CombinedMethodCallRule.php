<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Rules\Debug\BaseNoDebugRule;
use Hihaho\PhpstanRules\Traits\DetectsPositionalFlagArgument;
use Hihaho\PhpstanRules\Traits\ResolvesFormRequestRuleKeys;
use Illuminate\Http\Request;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Combined rule that handles all MethodCall checks in a single PHPStan dispatch,
 * eliminating the overhead of dispatching to separate rules for every method call.
 *
 * Merges: ChainedNoDebugInNamespaceRule + NoUnsafeRequestDataRule + UnvalidatedFormRequestFieldRule
 *
 * @extends BaseNoDebugRule<MethodCall>
 */
final readonly class CombinedMethodCallRule extends BaseNoDebugRule
{
    use DetectsPositionalFlagArgument;
    use ResolvesFormRequestRuleKeys;

    private const string DEBUG_MESSAGE = 'No chained debug statements should be present in the %s namespace.';

    /** @var array<string, true> */
    private array $unsafeMethodsLookup;

    /** @var array<string, true> */
    private array $fieldAccessorsLookup;

    /** @var array<string, true> */
    private array $quickRejectLookup;

    private ObjectType $requestType;

    /**
     * @param  list<string>  $unsafeMethods
     * @param  list<string>  $fieldAccessors
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     * @param  list<string>  $firstPartyNamespaces
     */
    public function __construct(
        array $unsafeMethods,
        array $fieldAccessors,
        private array $namespaces,
        private array $excludeNamespaces,
        private Parser $parser,
        private array $firstPartyNamespaces,
    ) {
        $this->unsafeMethodsLookup = array_fill_keys(array_map(strtolower(...), $unsafeMethods), true);
        $this->fieldAccessorsLookup = array_fill_keys(array_map(strtolower(...), $fieldAccessors), true);
        $this->requestType = new ObjectType(Request::class);
        $this->quickRejectLookup = $this->unsafeMethodsLookup + $this->fieldAccessorsLookup + self::METHOD_DEBUG_STATEMENTS;
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

        $errors = [];

        // Runs on every method call (not method-name gated) but bails cheaply
        // unless the final argument is a bare bool/null literal.
        $flagError = $this->positionalFlagErrorForMethodCall($node, $scope, $this->firstPartyNamespaces);
        if ($flagError instanceof IdentifierRuleError) {
            $errors[] = $flagError;
        }

        $methodName = $node->name->name;

        if (! isset($this->quickRejectLookup[strtolower($methodName)])) {
            return $errors;
        }

        $debugError = $this->checkDebugMethodCall($node, $methodName, $scope);
        if ($debugError instanceof IdentifierRuleError) {
            $errors[] = $debugError;
        }

        $requestError = $this->checkUnsafeRequestData($node, $methodName, $scope);
        if ($requestError instanceof IdentifierRuleError) {
            $errors[] = $requestError;
        }

        $fieldError = $this->checkUnvalidatedFormRequestField($node, $methodName, $scope);
        if ($fieldError instanceof IdentifierRuleError) {
            $errors[] = $fieldError;
        }

        return $errors;
    }

    /**
     * Mutually exclusive with checkUnsafeRequestData: that check bails when the
     * scope class is a Request (a FormRequest is-a Request), so it never fires
     * inside a FormRequest, while this one fires only inside a FormRequest.
     */
    private function checkUnvalidatedFormRequestField(MethodCall $node, string $methodName, Scope $scope): ?IdentifierRuleError
    {
        if (! isset($this->fieldAccessorsLookup[strtolower($methodName)])) {
            return null;
        }

        if (! $node->var instanceof Variable || $node->var->name !== 'this') {
            return null;
        }

        if (! $this->isInRequestNamespace($scope)) {
            return null;
        }

        $args = $node->getArgs();

        if ($args === []) {
            return null;
        }

        $keyArg = $args[0]->value;

        if (! $keyArg instanceof String_) {
            return null;
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        if (! $this->classIsFormRequest($classReflection->getName())) {
            return null;
        }

        $validatedRoots = $this->resolveValidatedRoots($this->parser, $classReflection, $scope);

        if ($validatedRoots === null) {
            return null;
        }

        if (isset($validatedRoots[$this->rootSegment($keyArg->value)])) {
            return null;
        }

        return $this->buildUnvalidatedFieldError($keyArg->value, $methodName);
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
