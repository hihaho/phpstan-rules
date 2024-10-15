<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Stringable;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
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
        if (str_starts_with($scope->getNamespace(), 'App\\Http\\Request')) {
            return [];
        }

        if (! $this->hasFormRequestClass($scope)) {
            return [];
        }

        if ($this->usesValidMethod(varName: $this->getName($node->var), methodName: $this->getName($node))) {
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
        if ($methodName === 'safe' && $this->isValidateMethod($methodName)) {
            return true;
        }

        if ($varName === 'request' && $methodName === 'safe') {
            return true;
        }

        return $varName === 'safe' && $this->isValidateMethod($methodName);
    }

    private function getName(MethodCall|Variable $var): string
    {
        if ($var->name instanceof Stringable) {
            return $var->name->toString();
        }

        if ($var->name instanceof Identifier) {
            return $var->name->toString();
        }

        return $var->name;
    }
}
