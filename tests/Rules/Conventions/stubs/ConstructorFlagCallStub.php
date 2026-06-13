<?php declare(strict_types=1);

namespace Vendor\Lib;

final class VendorThing
{
    public function __construct(string $a, bool $flag) {}
}

namespace App\Models;

use Vendor\Lib\VendorThing;

final class Widget
{
    public function __construct(public string $name, public bool $visible) {}
}

final class ConstructorFlagCallStub
{
    public function run(): void
    {
        new Widget('name', true);
        new VendorThing('name', true);
    }
}
