<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

final class CollectionListAllTarget
{
    /**
     * @param Collection<int, int> $collection
     * @param LazyCollection<int, string> $lazy
     * @param Enumerable<int, string> $enumerable
     */
    public function exercise(Collection $collection, LazyCollection $lazy, Enumerable $enumerable): void
    {
        // values()->all() on a Collection is re-keyed to a list.
        assertType('list<int>', $collection->values()->all());

        // LazyCollection is covered too.
        assertType('list<string>', $lazy->values()->all());

        // A non-values() re-keying call is not narrowed — all() keeps its array type.
        assertType('array<int, int>', $collection->keyBy(fn (int $value): int => $value)->all());

        // A plain all() (no re-keying call) is untouched.
        assertType('array<int, int>', $collection->all());

        // A bare Enumerable is not a known list-producing collection, so it is never narrowed
        // even though values() was called — the concrete key semantics are unknown.
        assertType('array<int, string>', $enumerable->values()->all());
    }
}
