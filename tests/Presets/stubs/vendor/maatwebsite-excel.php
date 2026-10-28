<?php declare(strict_types=1);

namespace Maatwebsite\Excel\Concerns;

/**
 * Minimal stand-in for maatwebsite/excel's Exportable trait.
 */
trait Exportable
{
    public function download(?string $fileName = null): mixed
    {
        return null;
    }
}

namespace Maatwebsite\Excel;

class Excel
{
    public function download(object $export, string $fileName): mixed
    {
        return null;
    }
}
