<?php declare(strict_types=1);

namespace App\Services;

final class FlagHelper
{
    public function setVisible(string $key, bool $visible): void {}
}

/**
 * @method void setHidden(string $key, bool $hidden)
 * @mixin FlagHelper
 */
final class MagicFlagTarget
{
    /** @param array<int, mixed> $arguments */
    public function __call(string $name, array $arguments): void {}

    protected function setGuarded(string $key, bool $guarded): void {}
}

final class LegacyFlagTarget
{
    /** @no-named-arguments */
    public function setLocked(string $key, bool $locked): void {}
}

class NativeFlagBase
{
    public function setShown(string $key, bool $shown): void {}
}

/**
 * @method void setShown(string $key, bool $renamed)
 */
final class AnnotatedFlagChild extends NativeFlagBase {}

final class MagicFlagCallStub
{
    public function run(MagicFlagTarget $magic, LegacyFlagTarget $legacy, FlagHelper $helper, AnnotatedFlagChild $child): void
    {
        $magic->setHidden('name', true);
        $magic->setVisible('name', true);
        $magic->setGuarded('name', true);
        $legacy->setLocked('name', true);

        $helper->setVisible('name', true);
        $child->setShown('name', true);
    }
}
