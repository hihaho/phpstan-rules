<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Illuminate\Support\Facades\Request as RequestFacade;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<StaticCall>
 */
final readonly class NoUnsafeRequestFacadeRule implements Rule
{
    use ChecksNamespace;

    /** @var list<string> */
    private array $unsafeMethodsLower;

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
        $this->unsafeMethodsLower = array_values(array_map(strtolower(...), $unsafeMethods));
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
        if (! $node->class instanceof Name) {
            return [];
        }

        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        if (! in_array(strtolower($methodName), $this->unsafeMethodsLower, true)) {
            return [];
        }

        if (strtolower($node->class->toString()) !== strtolower(RequestFacade::class)) {
            return [];
        }

        if (! $this->isInConfiguredNamespace($scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Reading unvalidated request data via ' . RequestFacade::class . '::%s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
                $methodName,
            ))
                ->identifier('hihaho.validation.noUnsafeRequestFacade')
                ->tip('Inject a FormRequest (or Request typehint) and consume via $request->validated() / $request->safe() instead of the Request facade.')
                ->build(),
        ];
    }

    private function isInConfiguredNamespace(Scope $scope): bool
    {
        return $this->namespaceStartsWithAny($scope, $this->namespaces)
            && ! $this->namespaceStartsWithAny($scope, $this->excludeNamespaces);
    }
}
