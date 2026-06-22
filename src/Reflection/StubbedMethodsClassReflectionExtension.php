<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Reflection;

use Override;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;

/**
 * Resolves instance methods that exist at runtime but not in PHPStan's reflection — Faker custom
 * providers (added via __call) and Laravel macros — so they no longer need a baseline entry, while
 * a typo'd method name (not in the configured set) still fails analysis. Instance methods only;
 * statically-called methods (e.g. facade __callStatic) are out of scope.
 *
 * Configure per consumer via the `stubbedMethods` parameter; nothing is resolved by default. A
 * return type of `static`, `$this`, or `self` is bound to the receiver — so a stubbed fluent method
 * (a `$this`-returning macro, a chainable Nova field method) keeps its chain typed instead of
 * widening; any other value is parsed as a normal PHPDoc type string.
 */
final class StubbedMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
    /**
     * @param array<string, array<string, string>> $stubbedMethods class name => (method name => return type)
     */
    public function __construct(
        private readonly TypeStringResolver $typeStringResolver,
        private array $stubbedMethods,
    ) {}

    #[Override]
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return isset($this->stubbedMethods[$classReflection->getName()][$methodName]);
    }

    #[Override]
    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $returnType = $this->stubbedMethods[$classReflection->getName()][$methodName];

        return new StubbedMethodReflection(
            $classReflection,
            $methodName,
            $this->resolveReturnType($returnType, $classReflection),
        );
    }

    /**
     * `static`/`$this` follow the receiver (late static binding) so fluent chains keep their type;
     * `self` is the configured class exactly. Anything else is a PHPDoc type string.
     */
    private function resolveReturnType(string $returnType, ClassReflection $classReflection): Type
    {
        return match ($returnType) {
            'static' => new StaticType($classReflection),
            '$this' => new ThisType($classReflection),
            'self' => new ObjectType($classReflection->getName()),
            default => $this->typeStringResolver->resolve($returnType),
        };
    }
}
