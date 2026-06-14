<?php declare(strict_types=1);

namespace App\Services;

final class SameLineToggle
{
    public function setActive(string $key, bool $active): void {}
}

final class SameLineNullsafeFlagsStub
{
    public ?SameLineToggle $a = null;

    public ?SameLineToggle $b = null;

    public function run(): void
    {
        $this->a?->setActive('x', true); $this->b?->setActive('y', true);
    }
}
