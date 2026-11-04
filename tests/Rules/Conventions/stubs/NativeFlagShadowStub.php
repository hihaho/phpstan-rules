<?php declare(strict_types=1);

namespace App\Shadow;

/**
 * @param  array<int, int>  $haystack
 */
function in_array(int $needle, array $haystack, bool $loose): bool
{
    return $loose && $haystack !== [] && $needle > 0;
}

final class NativeFlagShadowStub
{
    /**
     * @param  array<int, int>  $haystack
     */
    public function run(array $haystack): bool
    {
        return in_array(1, $haystack, true);
    }
}
