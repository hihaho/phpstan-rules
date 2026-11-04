<?php declare(strict_types=1);

namespace App\Services;

final class NativeFlagCustomMapStub
{
    /**
     * @param  array<int, int>  $haystack
     */
    public function run(array $haystack): void
    {
        array_keys($haystack, null);
        in_array(1, $haystack, true);
    }
}
