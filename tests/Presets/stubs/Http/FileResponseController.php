<?php declare(strict_types=1);

namespace App\Presets\Http;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UsersExport
{
    use Exportable;
}

final class FileResponseController
{
    public function __invoke(ResponseFactory $response, FilesystemAdapter $disk, Excel $excel, UsersExport $export): void
    {
        $response->download('/tmp/report.csv');
        $response->streamDownload(static function (): void {}, 'report.csv');
        $response->file('/tmp/report.pdf');
        $disk->download('report.csv');
        $disk->response('report.csv');
        Storage::download('report.csv');
        $excel->download($export, 'users.xlsx');
        $export->download('users.xlsx');
        new StreamedResponse(static function (): void {});
        new BinaryFileResponse('/tmp/report.pdf');
        $response->download('/tmp/logo.png'); // @phpstan-ignore hihaho.disallowed.fileResponse (static asset under 50 KB)
    }
}
