<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\NodeAbstract;
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
    protected array $allowedRequestMethods = [];

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

    protected function nodeName(CallLike|Expr|NodeAbstract $var): string
    {
        if (! property_exists($var, 'name')) {
            return '';
        }

        if ($var->name instanceof Stringable) {
            return $var->name->toString();
        }

        if ($var->name instanceof Identifier) {
            if (method_exists($var->name, 'toString')) {
                return $var->name->toString();
            }

            return $var->name->name;
        }

        if ($var->name instanceof Variable) {
            return $var->name->name;
        }

        if ($var->name instanceof Node\Expr\BinaryOp\Concat) {
            if (method_exists($var->name, 'toString')) {
                return $var->name->toString();
            }

            return '';
        }

        if ($var->name instanceof PropertyFetch) {
            if (method_exists($var->name, 'toString')) {
                return $var->name->toString();
            }

            return $var->name->name->toString();
        }

        if ($var instanceof PropertyFetch) {
            return $var->name->toString();
        }

        if (method_exists($var->name, 'toString')) {
            return $var->name->toString();
        }

        return $var->name ?? '';
    }

    protected function hasValidNamespace(?string $namespace): bool
    {
        if (! $namespace) {
            return false;
        }

        return str_starts_with($namespace, 'App\\Http\\Request');
    }

    protected function usesValidMethod(string $varName, string $methodName): bool
    {
        if (! in_array($varName, ['request', 'safe', 'validated'], true)) {
            return true;
        }

        if ($varName === 'request' && ($methodName === 'validated' || $methodName === 'validate')) {
            return true;
        }

        if ($varName === 'request' && $methodName === 'safe') {
            return true;
        }

        if ($varName === 'safe') {
            return $this->isValidateMethod($methodName);
        }

        return ! $this->isValidateMethod($methodName);
    }

    protected function isValidateMethod(string $methodName): bool
    {
        return in_array($methodName, $this->allowedRequestMethods, strict: true);
    }
}
