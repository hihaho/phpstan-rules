<?php declare(strict_types=1);

namespace App\Enums\Concerns;

trait HasLocalizationStub
{
    public function localizationKey(): string
    {
        return 'enums.stub';
    }
}
