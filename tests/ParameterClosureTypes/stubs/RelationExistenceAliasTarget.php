<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs;

use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;

use function PHPStan\Testing\assertType;

/**
 * humble-goat aliases `Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder`. The alias is
 * the same FQCN, so the extension narrows the closure parameter identically — proven here.
 */
final class RelationExistenceAliasTarget
{
    /**
     * @param EloquentQueryBuilder<Foo> $query
     */
    public function exercise(EloquentQueryBuilder $query): void
    {
        $query->whereHas('bars', function (EloquentQueryBuilder $q): void {
            assertType('Illuminate\Database\Eloquent\Builder<Hihaho\PhpstanRules\Tests\ParameterClosureTypes\stubs\Bar>', $q);
        });
    }
}
