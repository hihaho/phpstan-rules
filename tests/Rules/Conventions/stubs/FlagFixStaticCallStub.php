<?php declare(strict_types=1);

namespace App\Services;

final class FixableStaticTarget
{
    public static function make(string $key, bool $fresh = false, bool $lazy = false): void {}
}

final class FlagFixStaticCallStub
{
    public function run(): void
    {
        FixableStaticTarget::make('key', true);
        FixableStaticTarget::make('key', true, false);
        FixableStaticTarget::make('key', false, lazy: true);
    }
}
