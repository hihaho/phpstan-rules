<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Illuminate\Http\Request;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;
use PHPStan\Reflection\Php\PhpParameterFromParserNodeReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

final class ScopeRequestValidateMethods implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @throws ShouldNotHappenException */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($scope->getNamespace() === 'App\\Http\\Request') {
            return [];
        }

        if (! $this->hasIlluminateRequestClass($scope->getFunction())) {
            return [];
        }

        if (! $this->isBlacklisted($node->name->toString())) {
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

    /** @throws ShouldNotHappenException */
    private function hasIlluminateRequestClass(PhpFunctionFromParserNodeReflection|null $function): bool
    {
        if (! $function) {
            return false;
        }

        return ! empty(
            array_filter(
                array: $function->getParameters(),
                callback: static fn (PhpParameterFromParserNodeReflection $parameter): bool => in_array(
                    needle: Request::class,
                    haystack: $parameter->getType()->getReferencedClasses(),
                    strict: true
                )
            )
        );
    }

    private function isBlacklisted(string $methodName): bool
    {
        if ($methodName === 'only') {
            return true;
        }

        if ($methodName === 'input') {
            return true;
        }

        if ($methodName === 'get') {
            return true;
        }

        if ($methodName === 'string') {
            return true;
        }

        if ($methodName === 'integer') {
            return true;
        }

        if ($methodName === 'boolean') {
            return true;
        }

        return false;
    }
}
