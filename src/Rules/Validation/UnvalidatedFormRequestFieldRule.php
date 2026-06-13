<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Hihaho\PhpstanRules\Traits\ResolvesFormRequestRuleKeys;
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
use PHPStan\Rules\Rule;

/**
 * Flags reading a request field inside a FormRequest when that field's key is
 * never declared in the same class's rules() method — the field is silently
 * unvalidated. The inverse of NoUnsafeRequestDataRule, which bans reading
 * request data outside a FormRequest.
 *
 * @implements Rule<MethodCall>
 */
final readonly class UnvalidatedFormRequestFieldRule implements Rule
{
    use ChecksNamespace;
    use ResolvesFormRequestRuleKeys;

    /** @var array<string, true> */
    private array $fieldAccessorsLookup;

    /**
     * @param  list<string>  $fieldAccessors
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        array $fieldAccessors,
        private array $namespaces,
        private array $excludeNamespaces,
        private Parser $parser,
    ) {
        $this->fieldAccessorsLookup = array_fill_keys(array_map(strtolower(...), $fieldAccessors), true);
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

        if (! isset($this->fieldAccessorsLookup[strtolower($node->name->name)])) {
            return [];
        }

        if (! $this->isInConfiguredNamespace($scope)) {
            return [];
        }

        $error = $this->checkUnvalidatedField($node, $node->name->name, $scope);

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }

    private function checkUnvalidatedField(MethodCall $node, string $methodName, Scope $scope): ?IdentifierRuleError
    {
        if (! $node->var instanceof Variable || $node->var->name !== 'this') {
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

    private function isInConfiguredNamespace(Scope $scope): bool
    {
        return $this->namespaceStartsWithAny($scope, $this->namespaces)
            && ! $this->namespaceStartsWithAny($scope, $this->excludeNamespaces);
    }
}
