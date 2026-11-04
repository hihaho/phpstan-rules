<?php declare(strict_types=1);

namespace App\Services;

final class SkippedFlagTarget
{
    public function configure(bool $on, string $key = ''): void {}

    public function single(bool $on): void {}

    public function tagged(string $key, bool ...$flags): void {}

    /** @no-named-arguments */
    public function positionalOnly(string $key, bool $on = false): void {}
}

final class FlagSkippedCallsStub
{
    /**
     * @param  array<int, mixed>  $rest
     */
    public function run(SkippedFlagTarget $target, array $rest, string $method): void
    {
        $target->configure(true, 'key');
        $target->configure(...$rest);
        $target->configure('key', ...$rest);
        $target->tagged('key', true);
        $target->tagged('key', true, false);
        $target->single(true, false);
        $target->single(on: true);
        $target->configure(on: true, key: 'key');
        $target->single(...);
        $target->{$method}(true);
        $target->positionalOnly('key', true);
    }
}
