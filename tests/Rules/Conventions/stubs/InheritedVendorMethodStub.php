<?php declare(strict_types=1);

namespace Vendor\Base;

class Configurable
{
    public function configure(bool $enabled): void {}
}

namespace App\Services;

use Vendor\Base\Configurable;

final class AppConfigurable extends Configurable {}

final class InheritedVendorMethodStub
{
    public function run(): void
    {
        (new AppConfigurable())->configure(true);
    }
}
