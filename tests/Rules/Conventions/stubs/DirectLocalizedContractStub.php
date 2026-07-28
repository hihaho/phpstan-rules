<?php declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLocalizationStub;
use App\Enums\Contracts\LocalizedEnumContractStub;

enum DirectLocalizedContractStub: string implements LocalizedEnumContractStub
{
    use HasLocalizationStub;

    case Draft = 'draft';
}
