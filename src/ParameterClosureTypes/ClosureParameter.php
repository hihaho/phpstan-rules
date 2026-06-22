<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\ParameterClosureTypes;

use Override;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\Type;

/**
 * Minimal ParameterReflection for describing a single closure parameter in a synthesized ClosureType.
 *
 * ParameterReflection is the @api-stable interface; the concrete framework implementations
 * (NativeParameterReflection et al.) are not covered by PHPStan's backward-compatibility promise,
 * so we implement the interface directly to stay on supported surface.
 */
final readonly class ClosureParameter implements ParameterReflection
{
    public function __construct(private string $name, private Type $type) {}

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function isOptional(): bool
    {
        return false;
    }

    #[Override]
    public function getType(): Type
    {
        return $this->type;
    }

    #[Override]
    public function passedByReference(): PassedByReference
    {
        return PassedByReference::createNo();
    }

    #[Override]
    public function isVariadic(): bool
    {
        return false;
    }

    #[Override]
    public function getDefaultValue(): ?Type
    {
        return null;
    }
}
