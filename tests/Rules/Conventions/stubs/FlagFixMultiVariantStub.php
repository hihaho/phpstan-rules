<?php declare(strict_types=1);

namespace App\Services;

/**
 * Its `pick` method comes from a test-only reflection extension with one variant
 * per first-argument type, each naming the flag differently.
 */
final class MultiVariantFlagTarget {}

final class FlagFixMultiVariantStub
{
    public function run(MultiVariantFlagTarget $target): void
    {
        $target->pick('key', true);
        $target->pick(1, true);
    }
}
