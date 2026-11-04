<?php declare(strict_types=1);

namespace App\Services;

final class FixableNullsafeTarget
{
    public function configure(string $key, bool $nullable = false, bool $strict = false): void {}
}

final class FlagFixNullsafeCallStub
{
    public function run(?FixableNullsafeTarget $target): void
    {
        $target?->configure('key', true);
        $target?->configure('key', true, false);
    }
}
