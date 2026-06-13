<?php declare(strict_types=1);

namespace Vendor\Pkg;

final class Gadget
{
    public function method(string $a, bool $flag): void {}
}

namespace App\Services;

use Vendor\Pkg\Gadget;

final class NonFirstPartyCaller
{
    public function run(): void
    {
        (new Gadget())->method('x', true);
    }
}
