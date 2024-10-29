<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use ReflectionClass;
use ReflectionException;

final class ScopeFormRequestValidateMethods extends ScopeValidationMethods
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @phpstan-param MethodCall $node
     * @throws ReflectionException
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->hasValidNamespace($scope->getNamespace())) {
            return [];
        }

        if (! $this->hasFormRequestClass($scope)) {
            return [];
        }

        if ($this->usesValidMethod(varName: $this->nameFrom($node->var), methodName: $this->nameFrom($node))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Usage of unvalidated request data is not allowed outside of App\\Http\\Requests'
            )
                ->nonIgnorable()
                ->tip('Use $request->safe() to use request data')
                ->identifier('hihaho.request.unsafeRequestData')
                ->build(),
        ];
    }

    /** @throws ReflectionException */
    private function hasFormRequestClass(Scope $scope): bool
    {
        $parentClassName = static fn (ReflectionClass $fqn) => $fqn->getParentClass()->getName();

        return collect($this->getClassMethods($scope))
            ->map(fn (array $method) => $this->getMethodParameterClassnames($method))
            ->flatten()
            ->map(fn (string $className) => new ReflectionClass($className))
            ->filter(fn (ReflectionClass $className) => $parentClassName($className) === FormRequest::class)
            ->isNotEmpty();
    }

    private function usesValidMethod(string $varName, string $methodName): bool
    {
        if ($varName === 'request' && ($methodName === 'safe' || $methodName === 'validated')) {
            return true;
        }

        return $varName === 'safe' && $this->isValidateMethod($methodName);
    }
}
