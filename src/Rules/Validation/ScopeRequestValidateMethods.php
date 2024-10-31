<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Illuminate\Http\Request as IlluminateRequest;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use ReflectionException;

final class ScopeRequestValidateMethods extends ScopeValidationMethods
{
    public function __construct(array $allowedRequestMethods)
    {
        $this->allowedRequestMethods = array_unique($allowedRequestMethods);
    }

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

        if ($this->usesValidMethod(varName: $this->nodeName($node->var), methodName: $this->nodeName($node))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Usage of unvalidated request data is not allowed outside of App\\Http\\Requests'
            )
                ->nonIgnorable()
                ->addTip('Use $request->safe() / $request->validated() to use request data')
                ->addTip(sprintf('Current checking: variable %s, method %s', $this->nodeName($node->var), $this->nodeName($node)))
                ->identifier('hihaho.request.unsafeRequestData')
                ->build(),
        ];
    }

    /** @throws ReflectionException */
    private function hasIlluminateRequestClass(Scope $scope): bool
    {
        return collect($this->getClassMethods($scope))
            ->map(fn (array $method) => $this->getMethodParameterClassnames($method)
                ->filter(static fn (?string $fqn) => $fqn !== null)
                ->filter(static fn (string $fqn) => $fqn === IlluminateRequest::class)
            )
            ->isNotEmpty();
    }
}
