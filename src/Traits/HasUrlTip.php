<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

trait HasUrlTip
{
    public function docsTip(string $url): string
    {
        return "Learn more at {$url}";
    }
}
