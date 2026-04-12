<?php declare(strict_types=1);

namespace App;

final class UnknownReceiverChainedDumpStub
{
    /** @param mixed $value */
    public function test($value): void
    {
        // $value is typed as mixed — PHPStan cannot resolve any object class.
        // The narrowing must bail without flagging (no object reflections).
        $value->dump();
        $value->dd();
    }
}
