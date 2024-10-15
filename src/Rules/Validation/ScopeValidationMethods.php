<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Illuminate\Support\Collection;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
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
abstract class ScopeValidationMethods implements Rule
{
    abstract public function getNodeType(): string;

    abstract public function processNode(Node $node, Scope $scope): array;

    /**
     * @phpstan-return ReflectionMethod[]
     * @throws ReflectionException
     */
    protected function getClassMethods(Scope $scope): array
    {
        $type = new ObjectType(className: $scope->getClassReflection()?->getName(), classReflection: $scope->getClassReflection());
        /** @var ReflectionMethod[] $methods */
        $methods = array_map(static function (string $className): array {
            return (new ReflectionClass($className))->getMethods();
        }, $type->getObjectClassNames());

        return $methods;
    }

    protected function getMethodParameterClassnames(array $method): Collection
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

    protected function isValidateMethod(string $methodName): bool
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
