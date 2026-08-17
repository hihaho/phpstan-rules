<?php declare(strict_types=1);

namespace App\Services;

final class NullsafeChainToggle
{
    public ?NullsafeChainToggle $nested = null;

    public function self(): NullsafeChainToggle
    {
        return $this;
    }

    public function setActive(string $key, bool $active): void {}
}

final class NullsafeChainFlagCallStub
{
    public function run(NullsafeChainToggle $always, ?NullsafeChainToggle $maybe): void
    {
        $maybe?->self()->setActive('plainCallAfterNullsafeHop', true);
        $always->self()?->setActive('nullsafeHopAfterPlainCall', true);
        $maybe?->nested?->setActive('twoNullsafeHops', true);
    }
}
