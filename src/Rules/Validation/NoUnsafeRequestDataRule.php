<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

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
 * @implements Rule<MethodCall>
 */
final readonly class NoUnsafeRequestDataRule implements Rule
{
    use ChecksNamespace;

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

        if (! isset($this->unsafeMethodsLookup[strtolower($node->name->toString())])) {
            return [];
        }

        if (! $this->isInConfiguredNamespace($scope)) {
            return [];
        }

        if ($this->scopeClassIsRequest($scope)) {
            return [];
        }

        if (! $this->typeIsRequest($scope->getType($node->var))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Reading unvalidated request data via %s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
                $node->name->toString(),
            ))
                ->identifier('hihaho.validation.noUnsafeRequestData')
                ->tip('Use $request->validated() or $request->safe() to consume validated data. For Stringable/int/bool accessors, $request->safe()->string(\'key\') mirrors $request->string(\'key\') against validated input.')
                ->build(),
        ];
    }

    private function isInConfiguredNamespace(Scope $scope): bool
    {
        return $this->namespaceStartsWithAny($scope, $this->namespaces)
            && ! $this->namespaceStartsWithAny($scope, $this->excludeNamespaces);
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
        return $this->requestType->isSuperTypeOf(new ObjectType($className))->yes();
    }
}
