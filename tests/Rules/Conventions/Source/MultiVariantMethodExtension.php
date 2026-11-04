<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions\Source;

use App\Services\MultiVariantFlagTarget;
use Override;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

final class MultiVariantMethodExtension implements MethodsClassReflectionExtension
{
    #[Override]
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $classReflection->getName() === MultiVariantFlagTarget::class && $methodName === 'pick';
    }

    #[Override]
    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new MultiVariantMethodReflection($classReflection);
    }
}
