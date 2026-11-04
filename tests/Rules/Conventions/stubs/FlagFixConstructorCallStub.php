<?php declare(strict_types=1);

namespace App\Services;

final class FixableConstructorTarget
{
    public function __construct(public string $key, public bool $readOnly = false, public bool $lazy = false) {}
}

final class FlagFixConstructorCallStub
{
    public function run(): void
    {
        new FixableConstructorTarget('key', true);
        new FixableConstructorTarget('key', true, false);
        new FixableConstructorTarget('key', false, lazy: true);
    }
}
