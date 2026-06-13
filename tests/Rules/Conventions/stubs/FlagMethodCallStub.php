<?php declare(strict_types=1);

namespace App\Services;

final class FlagToggle
{
    public function setActive(string $key, bool $active): void {}

    public function configure(?bool $option): void {}

    public function label(bool $on, string $text): void {}

    public function variadic(bool ...$flags): void {}
}

final class FlagMethodCallStub
{
    public function run(): void
    {
        $toggle = new FlagToggle();

        $toggle->setActive('name', true);
        $toggle->configure(null);

        $toggle->setActive('name', active: false);
        $toggle->label(true, 'hello');
        $toggle->variadic(true);
    }
}
