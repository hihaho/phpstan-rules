<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

final class FileUploadReadersStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request): array
    {
        return [
            'attachment' => $request->file('attachment'),
            'all' => $request->allFiles(),
        ];
    }
}
