<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Illuminate\Http\Request as IlluminateRequest;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use ReflectionException;

final class ScopeIlluminateRequestValidateMethods extends ScopeValidationMethods
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @phpstan-param MethodCall $node
     * @throws ShouldNotHappenException | ReflectionException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->hasValidNamespace($scope->getNamespace())) {
            return [];
        }

        if (! $this->hasIlluminateRequestClass($scope)) {
            return [];
        }

        if (! $this->isValidateMethod($this->nameFrom($node))) {
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
    private function hasIlluminateRequestClass(Scope $scope): bool
    {
        return collect($this->getClassMethods($scope))
            ->map(fn (array $method) => $this->getMethodParameterClassnames($method)
                ->filter(fn (string $fqn) => $fqn === IlluminateRequest::class)
            )
            ->isNotEmpty();
    }
}
