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
     */
    public function __construct(
        array $unsafeMethods,
        private array $namespaces,
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
                'Reading unvalidated request data via Illuminate\Support\Facades\Request::%s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
                $methodName,
            ))
                ->identifier('hihaho.validation.noUnsafeRequestFacade')
                ->tip('Inject a FormRequest subclass, or call $request->validated() / $request->safe() before reading input.')
                ->build(),
        ];
    }

    private function isInConfiguredNamespace(Scope $scope): bool
    {
        foreach ($this->namespaces as $namespace) {
            if ($this->namespaceStartsWith($scope, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
