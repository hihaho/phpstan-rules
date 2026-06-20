<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Reflection;

use Hihaho\PhpstanRules\Tests\Reflection\StubbedMethodsClassReflectionExtensionTest;
use Override;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

/**
 * Resolves instance methods that exist at runtime but not in PHPStan's reflection — Faker custom
 * providers (added via __call) and Laravel macros — so they no longer need a baseline entry, while
 * a typo'd method name (not in the configured set) still fails analysis. Instance methods only;
 * statically-called methods (e.g. facade __callStatic) are out of scope.
 *
 * Configure per consumer via the `stubbedMethods` parameter; nothing is resolved by default.
 * @see StubbedMethodsClassReflectionExtensionTest
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
            $this->typeStringResolver->resolve($returnType),
        );
    }
}
