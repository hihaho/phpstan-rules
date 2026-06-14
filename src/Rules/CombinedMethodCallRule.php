<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Rules\Debug\BaseNoDebugRule;
use Hihaho\PhpstanRules\Traits\DetectsPositionalFlagArgument;
use Hihaho\PhpstanRules\Traits\DetectsUnsafeRequestData;
use Hihaho\PhpstanRules\Traits\ResolvesFormRequestRuleKeys;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Rules\IdentifierRuleError;

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
    use DetectsUnsafeRequestData;
    use ResolvesFormRequestRuleKeys;

    /** @var array<string, true> */
    private array $unsafeMethodsLookup;

    /** @var array<string, true> */
    private array $fieldAccessorsLookup;

    /** @var array<string, true> */
    private array $quickRejectLookup;

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

        $debugError = $this->chainedDebugError($node, $methodName, $scope);
        if ($debugError instanceof IdentifierRuleError) {
            $errors[] = $debugError;
        }

        $requestError = $this->unsafeRequestDataError($node, $methodName, $scope, $this->unsafeMethodsLookup, $this->namespaces, $this->excludeNamespaces);
        if ($requestError instanceof IdentifierRuleError) {
            $errors[] = $requestError;
        }

        $fieldError = $this->unvalidatedFormRequestFieldError($node, $methodName, $scope, $this->parser, $this->fieldAccessorsLookup, $this->namespaces, $this->excludeNamespaces);
        if ($fieldError instanceof IdentifierRuleError) {
            $errors[] = $fieldError;
        }

        return $errors;
    }
}
