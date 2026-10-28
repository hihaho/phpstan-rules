<?php declare(strict_types=1);

namespace App\Exceptions\Stubs;

use Throwable;

final class SwallowedIgnoredStub
{
    public function warmCache(): void
    {
        try {
            $this->work();
        } catch (Throwable) { // @phpstan-ignore hihaho.exceptions.swallowedException (best-effort cache warmup)
        }
    }

    private function work(): void {}
}
