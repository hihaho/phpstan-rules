<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Rules\Debug\BaseNoDebugRule;
use Hihaho\PhpstanRules\Traits\DetectsInvadeUsage;
use Hihaho\PhpstanRules\Traits\DetectsUnsafeRequestHelper;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;

/**
 * Combined rule that handles all FuncCall checks in a single PHPStan dispatch,
 * eliminating the overhead of dispatching to three separate rules for every function call.
 *
 * Merges: NoDebugInNamespaceRule + NoInvadeInAppCode + NoUnsafeRequestHelperRule
 *
 * @extends BaseNoDebugRule<FuncCall>
 */
final readonly class CombinedFuncCallRule extends BaseNoDebugRule
{
    use DetectsInvadeUsage;
    use DetectsUnsafeRequestHelper;

    /**
     * Quick-reject lookup: unqualified calls not in this set can be skipped without checking
     * each sub-rule. Qualified names (containing backslash) are always passed through.
     * Built from FUNCTION_DEBUG_STATEMENTS so a new debug function added to the base
     * is automatically included here.
     *
     * @var array<string, true>
     */
    private array $interestingFuncNames;

    /**
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        private array $namespaces,
        private array $excludeNamespaces,
        private ReflectionProvider $reflectionProvider,
    ) {
        $this->interestingFuncNames = self::FUNCTION_DEBUG_STATEMENTS + [
            'invade' => true,
            'Livewire\\invade' => true,
            'request' => true,
        ];
    }

    #[Override]
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param  FuncCall  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Name) {
            return [];
        }

        $funcName = $node->name->name;

        // Quick reject: skip calls no sub-rule could match. The request-helper
        // check is case-insensitive on the last name segment, so a mixed-case
        // `\REQUEST(...)` must pass through here too — gating it case-sensitively
        // would silently drop those calls.
        if (
            ! isset($this->interestingFuncNames[$funcName])
            && strtolower($node->name->getLast()) !== 'request'
            && ! str_contains($funcName, '\\')
        ) {
            return [];
        }

        $errors = [];

        $debugError = $this->funcDebugError($funcName, $scope);
        if ($debugError instanceof IdentifierRuleError) {
            $errors[] = $debugError;
        }

        $invadeError = $this->invadeUsageError($funcName, $scope);
        if ($invadeError instanceof IdentifierRuleError) {
            $errors[] = $invadeError;
        }

        $requestError = $this->unsafeRequestHelperError($node, $node->name, $scope, $this->reflectionProvider, $this->namespaces, $this->excludeNamespaces);
        if ($requestError instanceof IdentifierRuleError) {
            $errors[] = $requestError;
        }

        return $errors;
    }
}
