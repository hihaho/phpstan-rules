<?php declare(strict_types=1);

namespace App\Services;

final class NativeFlagCallsStub
{
    /**
     * @param  array<int, int>  $haystack
     * @param  array<int, mixed>  $rest
     */
    public function run(array $haystack, string $json, array $rest): void
    {
        in_array(1, $haystack, true);
        in_array(1, $haystack, false);
        \in_array(1, $haystack, true);
        \IN_ARRAY(1, $haystack, true);
        array_search(1, $haystack, true);
        json_decode($json, true);
        json_decode($json, null);

        json_decode($json, true, 512);
        in_array(1, $haystack);
        in_array(1, $haystack, ...$rest);
        in_array(1, $haystack, strict: true);
        in_array(1, $haystack, $rest !== []);
        array_filter($rest, null);
        $check = in_array(...);
    }
}
