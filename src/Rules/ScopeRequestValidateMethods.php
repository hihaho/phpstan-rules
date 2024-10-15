<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\MethodCall>
 */
final class ScopeRequestValidateMethods implements Rule
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
     * @phpstan-return ReflectionMethod[]
     * @throws ReflectionException
     */
    private function getClassMethods(Scope $scope): array
    {
        $type = new ObjectType(className: $scope->getClassReflection()?->getName(), classReflection: $scope->getClassReflection());
        /** @var ReflectionMethod[] $methods */
        $methods = array_map(static function (string $className): array {
            return (new ReflectionClass($className))->getMethods();
        }, $type->getObjectClassNames());

        return $methods;
    }

    private function getMethodParameterClassnames(array $method): Collection
    {
        return collect($method)
            ->map(fn (ReflectionMethod $method) => $method->getParameters())
            ->map(fn (array $parameter) => array_map(static function (ReflectionParameter $parameter) {
                /** @var ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|null $type */
                $type = $parameter->getType();

                return $type?->getName();
            }, $parameter))
            ->flatten();
    }

    private function isBlacklistedMethod(string $methodName): bool
    {
        $blacklistedMethodNames = [
            'collect',
            'all',
            'only',
            'except',
            'input',
            'get',
            'keys',
            'string',
            'str',
            'integer',
            'float',
            'boolean',
        ];

        return in_array($methodName, $blacklistedMethodNames, strict: true);
    }
}
