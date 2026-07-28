<?php declare(strict_types=1);

namespace App\Enums\Concerns;

trait SideMenuStub
{
    use HasLocalizationStub;

    public function menuIcon(): string
    {
        return 'heroicon-o-cog';
    }
}
