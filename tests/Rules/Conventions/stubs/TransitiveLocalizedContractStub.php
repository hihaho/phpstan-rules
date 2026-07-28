<?php declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLocalizationStub;
use App\Enums\Contracts\BaseActionTypeContractStub;

enum TransitiveLocalizedContractStub: int implements BaseActionTypeContractStub
{
    use HasLocalizationStub;

    case Click = 1;
}
