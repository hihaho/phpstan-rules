<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Support\Stringable;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use ReflectionClass;
use ReflectionException;

final class ScopeRequestValidateMethods extends ScopeValidationMethods
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @throws ShouldNotHappenException | ReflectionException */
    public function processNode(Node $node, Scope $scope): array
    {
        if (str_starts_with($scope->getNamespace(), 'App\\Http\\Request')) {
            return [];
        }

        if (! $this->hasRequestClass($scope)) {
            return [];
        }

        if ($this->usesValidatedMethod($node) && $this->isBlacklistedMethod($node->name->toString())) {
            return [];
        }

        if (! $this->isBlacklistedMethod($node->name->toString())) {
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
    private function hasRequestClass(Scope $scope): bool
    {
        return $this->hasIlluminateRequestClass($scope) || $this->hasFormRequestClass($scope);
    }

    /** @throws ReflectionException */
    private function hasIlluminateRequestClass(Scope $scope): bool
    {
        return collect($this->getClassMethods($scope))
            ->map(fn (array $method) => $this->getMethodParameterClassnames($method)
                ->filter(fn (string $fqn) => $fqn === IlluminateRequest::class)
            )
            ->isNotEmpty();
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

    /**
     * @phpstan-param MethodCall $node
     */
    private function usesValidatedMethod(Node $node): bool
    {
        /** @var Node\Expr\Variable $var */
        $var = $node->var;
        if ($var->name instanceof Stringable) {
            return $var->name->toString() === 'safe';
        }

        if ($var->name instanceof Node\Identifier) {
            return $var->name->toString() === 'safe';
        }

        return $var->name === 'safe';
    }
}
