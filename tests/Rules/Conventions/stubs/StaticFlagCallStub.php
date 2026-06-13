<?php declare(strict_types=1);

namespace Vendor\Lib;

final class StaticGadget
{
    public static function run(string $a, bool $flag): void {}
}

namespace App\Support;

use Vendor\Lib\StaticGadget;

final class StaticFlag
{
    public static function toggle(string $key, bool $on): void {}
}

final class StaticFlagCallStub
{
    public function run(): void
    {
        StaticFlag::toggle('name', false);
        StaticGadget::run('name', true);
    }
}
