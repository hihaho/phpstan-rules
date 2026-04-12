<?php declare(strict_types=1);

namespace Vendor\Package;

use stdClass;

use function Livewire\invade;

class LivewireInvadeInVendorNamespace
{
    public function test(): void
    {
        $obj = new stdClass();
        invade($obj);
    }
}
