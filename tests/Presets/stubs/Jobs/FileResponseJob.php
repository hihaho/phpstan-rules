<?php declare(strict_types=1);

namespace App\Presets\Jobs;

use Illuminate\Contracts\Routing\ResponseFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Outside the configured HTTP paths, so nothing here is reported.
 */
final class FileResponseJob
{
    public function handle(ResponseFactory $response): void
    {
        $response->download('/tmp/report.csv');
        new StreamedResponse(static function (): void {});
    }
}
