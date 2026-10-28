<?php declare(strict_types=1);

namespace App\Presets;

use Illuminate\Support\Str;

final class StrLimitStub
{
    public function cap(string $value): void
    {
        Str::limit($value, 255);
        Str::of($value)->limit(255);
        mb_substr($value, 0, 255);
        Str::limit($value, 40); // @phpstan-ignore hihaho.disallowed.strLimit (display label, width is the point)
    }
}
