<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Rules\Debug\BaseNoDebugRule;
use Hihaho\PhpstanRules\Support\TrailingFlagRun;
use Hihaho\PhpstanRules\Traits\DetectsInvadeUsage;
use Hihaho\PhpstanRules\Traits\DetectsPositionalFlagArgument;
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
 * Merges: NoDebugInNamespaceRule + NoInvadeInAppCode + NoUnsafeRequestHelperRule,
 * plus the positional flag argument check for native functions.
 *
 * @extends BaseNoDebugRule<FuncCall>
 */
final readonly class CombinedFuncCallRule extends BaseNoDebugRule
{
    use DetectsInvadeUsage;
    use DetectsPositionalFlagArgument;
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
     * Lowercased function name => (position of the flag => its parameter name).
     *
     * @var array<string, array<int, string>>
     */
    private array $nativeFunctionFlags;

    /**
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     * @param  array<string, array<int, string>>  $nativeFunctionFlags
     */
    public function __construct(
        private array $namespaces,
        private array $excludeNamespaces,
        private ReflectionProvider $reflectionProvider,
        array $nativeFunctionFlags,
    ) {
        $this->nativeFunctionFlags = array_change_key_case($nativeFunctionFlags);
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

        // Quick reject: skip calls no sub-rule could match. The request-helper and
        // native flag checks are case-insensitive on the last name segment, so a
        // mixed-case `\REQUEST(...)` or `\IN_ARRAY(...)` must pass through here too
        // — gating them case-sensitively would silently drop those calls.
        $lastSegment = strtolower($node->name->getLast());

        if (
            ! isset($this->interestingFuncNames[$funcName])
            && $lastSegment !== 'request'
            && ! isset($this->nativeFunctionFlags[$lastSegment])
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

        $flagError = $this->nativeFlagError($node, $node->name, $scope);
        if ($flagError instanceof IdentifierRuleError) {
            $errors[] = $flagError;
        }

        return $errors;
    }

    /**
     * Only the last argument qualifies: naming anything earlier would leave a
     * positional argument behind a named one. Resolving the name keeps a namespaced
     * function that shadows the native one (`App\in_array`) out of it.
     */
    private function nativeFlagError(FuncCall $node, Name $name, Scope $scope): ?IdentifierRuleError
    {
        if ($node->isFirstClassCallable()) {
            return null;
        }

        $args = $node->getArgs();
        $lastIndex = array_key_last($args);

        if ($lastIndex === null || ! TrailingFlagRun::isBareFlag($args[$lastIndex])) {
            return null;
        }

        $resolvedName = $this->reflectionProvider->resolveFunctionName($name, $scope);
        $paramName = $resolvedName === null ? null : ($this->nativeFunctionFlags[strtolower($resolvedName)][$lastIndex] ?? null);

        return $paramName === null ? null : $this->flagError([['arg' => $args[$lastIndex], 'paramName' => $paramName]]);
    }
}
