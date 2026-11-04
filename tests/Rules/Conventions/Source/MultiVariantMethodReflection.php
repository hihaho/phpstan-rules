<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions\Source;

use Hihaho\PhpstanRules\ParameterClosureTypes\ClosureParameter;
use Override;
use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\TrinaryLogic;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\VoidType;

/**
 * A method with two overloads whose flag parameter is named differently, so a test
 * can assert the fix follows the variant selected from the arguments.
 */
final readonly class MultiVariantMethodReflection implements MethodReflection
{
    public function __construct(private ClassReflection $declaringClass) {}

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
        return 'pick';
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
            $this->variant(new StringType(), 'byName'),
            $this->variant(new IntegerType(), 'byIndex'),
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

    private function variant(Type $keyType, string $flagName): FunctionVariant
    {
        return new FunctionVariant(
            TemplateTypeMap::createEmpty(),
            TemplateTypeMap::createEmpty(),
            [
                new ClosureParameter('key', $keyType),
                new ClosureParameter($flagName, new BooleanType()),
            ],
            false,
            new VoidType(),
        );
    }
}
