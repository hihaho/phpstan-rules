<?php declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\SideMenuStub;

abstract class BaseInheritedTraitStub
{
    use SideMenuStub;
}

final class InheritedTraitStub extends BaseInheritedTraitStub {}
