<?php declare(strict_types=1);

namespace App\Services;

final class FixableToggle
{
    public function configure(string $key, bool $nullable = false, bool $strict = false): self
    {
        return $this;
    }

    public function toggle(?bool $on): self
    {
        return $this;
    }

    public function combine(bool $left, bool $right): bool
    {
        return $left && $right;
    }
}

final class FlagFixMethodCallStub
{
    public function run(FixableToggle $toggle): void
    {
        $toggle->configure('key', true, FALSE);
        $toggle->configure('key', false, strict: true);
        $toggle->toggle(null);
        $toggle->toggle($toggle->combine(true, false));
        $toggle->combine($toggle->combine(true, false), true);
        $toggle->toggle(true); $toggle->toggle(false);

        $toggle
            ->configure(
                'key',
                true,
            )
            // keeps this comment
            ->toggle(false);
    }
}
