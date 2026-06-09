<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Rules\Debug\BaseNoDebugRule;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

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
    private const string DEBUG_MESSAGE = 'No debug statements should be present in the %s namespace.';

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

        if (! isset($this->interestingFuncNames[$funcName]) && ! str_contains($funcName, '\\')) {
            return [];
        }

        $errors = [];

        $debugError = $this->checkDebugStatement($funcName, $scope);
        if ($debugError instanceof IdentifierRuleError) {
            $errors[] = $debugError;
        }

        $invadeError = $this->checkInvadeUsage($funcName, $scope);
        if ($invadeError instanceof IdentifierRuleError) {
            $errors[] = $invadeError;
        }

        $requestError = $this->checkRequestHelper($node, $node->name, $scope);
        if ($requestError instanceof IdentifierRuleError) {
            $errors[] = $requestError;
        }

        return $errors;
    }

    private function checkDebugStatement(string $funcName, Scope $scope): ?IdentifierRuleError
    {
        if (! $this->isDebugFunction($funcName)) {
            return null;
        }

        $namespace = $this->matchDebugNamespace($scope);

        if ($namespace === null) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(self::DEBUG_MESSAGE, $namespace))
            ->identifier("hihaho.debug.noDebugIn{$namespace}")
            ->build();
    }

    private function checkInvadeUsage(string $funcName, Scope $scope): ?IdentifierRuleError
    {
        if ($funcName === 'Livewire\invade') {
            return RuleErrorBuilder::message(
                'Usage of `\Livewire\invade` is disallowed, please use the global `invade` from spatie/invade.'
            )
                ->identifier('hihaho.generic.disallowedUsageOfLivewireInvade')
                ->build();
        }

        if ($funcName === 'invade' && $this->namespaceStartsWith($scope, 'App')) {
            return RuleErrorBuilder::message(
                'Usage of method `invade` is not allowed in the App namespace.'
            )
                ->identifier('hihaho.generic.noInvadeInAppCode')
                ->build();
        }

        return null;
    }

    private function checkRequestHelper(FuncCall $node, Name $name, Scope $scope): ?IdentifierRuleError
    {
        if ($node->getArgs() === []) {
            return null;
        }

        if (strtolower($name->getLast()) !== 'request') {
            return null;
        }

        if (! $this->isInConfiguredNamespace($scope)) {
            return null;
        }

        if (! $this->reflectionProvider->hasFunction($name, $scope)) {
            return null;
        }

        if (strtolower($this->reflectionProvider->getFunction($name, $scope)->getName()) !== 'request') {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(
            'Reading unvalidated request data via %s is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
            $this->callLabel($node),
        ))
            ->identifier('hihaho.validation.noUnsafeRequestHelper')
            ->tip('Inject a FormRequest (or Request typehint) and consume via $request->validated() / $request->safe() instead of the global helper.')
            ->build();
    }

    private function callLabel(FuncCall $node): string
    {
        $firstArg = $node->getArgs()[0]->value;

        if ($firstArg instanceof String_) {
            return "request('{$firstArg->value}')";
        }

        return 'request(...)';
    }

    private function isInConfiguredNamespace(Scope $scope): bool
    {
        return $this->namespaceStartsWithAny($scope, $this->namespaces)
            && ! $this->namespaceStartsWithAny($scope, $this->excludeNamespaces);
    }
}
