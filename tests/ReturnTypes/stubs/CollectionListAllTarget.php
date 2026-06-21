<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function PHPStan\Testing\assertType;

final class CollectionListAllTarget
{
    /**
     * @param Collection<int, int> $collection
     * @param LazyCollection<int, string> $lazy
     * @param EloquentCollection<int, int> $eloquent
     */
    public function exercise(Collection $collection, LazyCollection $lazy, EloquentCollection $eloquent): void
    {
        // values()->all() on a Collection is re-keyed to a list.
        assertType('list<int>', $collection->values()->all());

        // LazyCollection is covered too.
        assertType('list<string>', $lazy->values()->all());

        // Subclasses that inherit values() unchanged (e.g. Eloquent collections) are narrowed too —
        // this is the common, intended case behind including subclasses.
        assertType('list<int>', $eloquent->values()->all());

        // A non-values() re-keying call is not narrowed — all() keeps its array type.
        assertType('array<int, int>', $collection->keyBy(fn (int $value): int => $value)->all());

        // A plain all() (no re-keying call) is untouched.
        assertType('array<int, int>', $collection->all());

        // A chain split across variables is left alone rather than guessed — detection is syntactic,
        // so all() on a plain variable falls back to the default array type (fails safe).
        $values = $collection->values();
        assertType('array<int, int>', $values->all());
    }
}
