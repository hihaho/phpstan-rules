<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Rules\Debug\BaseNoDebugRule;
use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Combined rule that handles all FuncCall checks in a single PHPStan dispatch,
 * eliminating the overhead of dispatching to three separate rules for every function call.
 *
 * Merges: NoDebugInNamespaceRule + NoInvadeInAppCode + NoUnsafeRequestHelperRule
 *
 * @implements Rule<FuncCall>
 */
final readonly class CombinedFuncCallRule extends BaseNoDebugRule implements Rule
{
    use ChecksNamespace;

    private const string DEBUG_MESSAGE = 'No debug statements should be present in the %s namespace.';

    /**
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        private array $namespaces,
        private array $excludeNamespaces,
        private ReflectionProvider $reflectionProvider,
    ) {}

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
        $errors = [];

        // --- NoDebugInNamespaceRule ---
        if ($this->isDebugFunction($funcName)) {
            if ($this->namespaceStartsWith($scope, 'App')) {
                $errors[] = RuleErrorBuilder::message(sprintf(self::DEBUG_MESSAGE, 'App'))
                    ->identifier('hihaho.debug.noDebugInApp')
                    ->build();
            } elseif ($this->namespaceStartsWith($scope, 'Tests')) {
                $errors[] = RuleErrorBuilder::message(sprintf(self::DEBUG_MESSAGE, 'Tests'))
                    ->identifier('hihaho.debug.noDebugInTests')
                    ->build();
            }
        }

        // --- NoInvadeInAppCode ---
        if ($funcName === 'Livewire\invade') {
            $errors[] = RuleErrorBuilder::message(
                'Usage of `\Livewire\invade` is disallowed, please use the global `invade` from spatie/invade.'
            )
                ->identifier('hihaho.generic.disallowedUsageOfLivewireInvade')
                ->build();
        } elseif ($funcName === 'invade' && $this->namespaceStartsWith($scope, 'App')) {
            $errors[] = RuleErrorBuilder::message(
                'Usage of method `invade` is not allowed in the App namespace.'
            )
                ->identifier('hihaho.generic.noInvadeInAppCode')
                ->build();
        }

        // --- NoUnsafeRequestHelperRule ---
        if ($node->getArgs() !== []
            && strtolower($node->name->getLast()) === 'request'
            && $this->isInConfiguredNamespace($scope)
            && $this->reflectionProvider->hasFunction($node->name, $scope)
            && strtolower($this->reflectionProvider->getFunction($node->name, $scope)->getName()) === 'request'
        ) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Reading unvalidated request data via %s is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
                $this->callLabel($node),
            ))
                ->identifier('hihaho.validation.noUnsafeRequestHelper')
                ->tip('Inject a FormRequest (or Request typehint) and consume via $request->validated() / $request->safe() instead of the global helper.')
                ->build();
        }

        return $errors;
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
