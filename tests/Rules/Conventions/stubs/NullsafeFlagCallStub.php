<?php declare(strict_types=1);

namespace App\Services;

final class NullsafeFlagToggle
{
    public function setActive(string $key, bool $active): void {}
}

final class NullsafeFlagCallStub
{
    public ?NullsafeFlagToggle $toggle = null;

    public function run(): void
    {
        $this->toggle?->setActive('name', true);
    }
}
