<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Request;

final class FileUploadFacadeStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'attachment' => Request::file('attachment'),
            'all' => Request::allFiles(),
        ];
    }
}
