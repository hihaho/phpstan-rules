<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Request as RequestFacade;

final class RequestFacadeAliasedImportStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'direct' => RequestFacade::boolean('direct'),
            'all' => RequestFacade::all(),
        ];
    }
}
