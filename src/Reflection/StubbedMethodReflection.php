<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Reflection;

use Override;
use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;

/**
 * A synthetic method declared by {@see StubbedMethodsClassReflectionExtension}. Accepts any
 * arguments (variadic) and returns the configured type, so dynamically-registered methods
 * (Faker providers, framework macros) resolve without their names being guessed away.
 */
final readonly class StubbedMethodReflection implements MethodReflection
{
    public function __construct(
        private ClassReflection $declaringClass,
        private string $name,
        private Type $returnType,
    ) {}

    #[Override]
    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    #[Override]
    public function isStatic(): bool
    {
        return false;
    }

    #[Override]
    public function isPrivate(): bool
    {
        return false;
    }

    #[Override]
    public function isPublic(): bool
    {
        return true;
    }

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    /**
     * @return list<ParametersAcceptor>
     */
    #[Override]
    public function getVariants(): array
    {
        return [
            new FunctionVariant(
                TemplateTypeMap::createEmpty(),
                TemplateTypeMap::createEmpty(),
                [],
                true,
                $this->returnType,
            ),
        ];
    }

    #[Override]
    public function getDocComment(): ?string
    {
        return null;
    }

    #[Override]
    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    #[Override]
    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    #[Override]
    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    #[Override]
    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    #[Override]
    public function getThrowType(): ?Type
    {
        return null;
    }

    #[Override]
    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createMaybe();
    }
}
