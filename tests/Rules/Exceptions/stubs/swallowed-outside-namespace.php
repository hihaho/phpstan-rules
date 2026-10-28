<?php declare(strict_types=1);

namespace Vendor\Library;

use Throwable;

final class SwallowedOutsideNamespaceStub
{
    public function emptyBody(): void
    {
        try {
            $this->work();
        } catch (Throwable) {
        }
    }

    private function work(): void {}
}
