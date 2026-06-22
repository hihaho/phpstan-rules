<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\HasBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function PHPStan\Testing\assertType;

final class Foo extends Model
{
    /**
     * @return HasMany<Bar, $this>
     */
    public function bars(): HasMany
    {
        return $this->hasMany(Bar::class);
    }

    /**
     * @return HasMany<Qux, $this>
     */
    public function quxes(): HasMany
    {
        return $this->hasMany(Qux::class);
    }
}

/**
 * @extends Builder<Qux>
 */
final class QuxQueryBuilder extends Builder {}

/**
 * A related model with a custom Eloquent builder (Laravel's HasBuilder trait).
 *
 * @use HasBuilder<QuxQueryBuilder>
 */
final class Qux extends Model
{
    /** @use HasBuilder<QuxQueryBuilder> */
    use HasBuilder;

    protected static string $builder = QuxQueryBuilder::class;
}

final class Bar extends Model
{
    /**
     * @return HasMany<Baz, $this>
     */
    public function bazzes(): HasMany
    {
        return $this->hasMany(Baz::class);
    }
}

final class Baz extends Model {}

/**
 * @extends Builder<Foo>
 */
final class FooQueryBuilder extends Builder {}

final class RelationExistenceClosureTarget
{
    /**
     * @param Builder<Foo> $query
     */
    public function exercise(Builder $query, FooQueryBuilder $customQuery, Foo $foo): void
    {
        // Core case: a bare `Builder` hint on the closure is narrowed to the related model's builder.
        $query->whereHas('bars', function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Bar>', $q);
        });

        // Arrow functions are preserved and narrowed the same way.
        $query->whereHas('bars', fn (Builder $q) => assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Bar>', $q));

        // The whole relationship-existence family shares the same callback param. `doesntHave` and
        // `has` take the callback in a later position (after $boolean/$operator) — resolved by name.
        $query->orWhereHas('bars', function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Bar>', $q);
        });
        $query->whereDoesntHave('bars', function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Bar>', $q);
        });
        $query->orWhereDoesntHave('bars', function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Bar>', $q);
        });
        $query->doesntHave('bars', 'and', function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Bar>', $q);
        });
        $query->has('bars', '>=', 1, 'and', function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Bar>', $q);
        });

        // Dotted nested relations resolve segment by segment to the last related model.
        $query->whereHas('bars.bazzes', function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Baz>', $q);
        });

        // A custom builder subclass (extends Builder<Foo>) is narrowed the same way.
        $customQuery->whereHas('bars', function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Bar>', $q);
        });

        // Called on a relation ($foo->bars()): the receiver's related model (Bar) scopes the resolution.
        $foo->bars()->whereHas('bazzes', function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Baz>', $q);
        });

        // A related model with a custom builder keeps that builder type (not the base Builder),
        // so relation-specific builder methods inside the closure still resolve.
        $query->whereHas('quxes', function (Builder $q): void {
            assertType(QuxQueryBuilder::class, $q);
        });

        // Named arguments, even out of order, resolve the relation by name (not by position).
        $query->whereHas(relation: 'bars', callback: function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Bar>', $q);
        });

        // Fail-safe: an unknown relation name is not narrowed — the default Builder<Model> stands,
        // so nothing is widened and real errors elsewhere are preserved.
        $query->whereHas('missing', function (Builder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Illuminate\Database\Eloquent\Model>', $q);
        });
    }
}
