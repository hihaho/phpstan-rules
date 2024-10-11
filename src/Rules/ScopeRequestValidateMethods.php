<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

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

        if (! $this->hasIlluminateRequestClass($scope)) {
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

    /** @throws ReflectionException */
    private function hasIlluminateRequestClass(Scope $scope): bool
    {
        return collect($this->getMethods($scope))
            ->map(fn (array $method) => $this->processMethod($method)
                ->filter(fn (string $fqn) => $fqn === IlluminateRequest::class)
            )
            ->isNotEmpty();
    }

    /**
     * @phpstan-return ReflectionMethod[]
     * @throws ReflectionException
     */
    private function getMethods(Scope $scope): array
    {
        $type = new ObjectType(className: $scope->getClassReflection()?->getName(), classReflection: $scope->getClassReflection());
        /** @var ReflectionMethod[] $methods */
        $methods = array_map(static function (string $className): array {
            return (new ReflectionClass($className))->getMethods();
        }, $type->getObjectClassNames());

        return $methods;
    }

    private function processMethod(array $method): Collection
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

    private function isBlacklisted(string $methodName): bool
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
