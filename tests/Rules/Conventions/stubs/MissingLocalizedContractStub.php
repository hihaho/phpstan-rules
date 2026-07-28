<?php declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLocalizationStub;

enum MissingLocalizedContractStub: string
{
    use HasLocalizationStub;

    case Draft = 'draft';
}
