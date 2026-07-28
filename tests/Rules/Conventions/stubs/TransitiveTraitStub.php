<?php declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\SideMenuStub;

enum TransitiveTraitStub: string
{
    use SideMenuStub;

    case Settings = 'settings';
}
