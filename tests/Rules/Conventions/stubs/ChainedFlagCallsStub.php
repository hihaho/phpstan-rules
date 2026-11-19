<?php declare(strict_types=1);

namespace App\Services;

final class ChainedFlags
{
    public function first(string $key, bool $on): self
    {
        return $this;
    }

    public function second(string $key, bool $off): self
    {
        return $this;
    }
}

final class ChainedFlagCallsStub
{
    public function run(ChainedFlags $flags): void
    {
        $flags->first('a', true)->second('b', false);
    }
}
