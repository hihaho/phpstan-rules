<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeFinder;

/**
 * Reads the closures a loop feeds to `Schema::table()`. The columns of a resumable
 * migration are often held as a map so each one can be guarded on its own:
 *
 *     foreach ($this->columns as $column => $definition) {
 *         if (Schema::hasColumn($this->table, $column)) {
 *             continue;
 *         }
 *
 *         Schema::table($this->table, $definition);
 *     }
 *
 * The list is read only when it is beyond doubt: a single array literal, either the
 * property's default or one assignment to it. A property written in more than one
 * place, or filled from a method call, is left unresolved — the list reaching the
 * loop is then not the list that was read, and the rule reports that it cannot check
 * the call rather than checking the wrong thing.
 *
 * @internal collaborator of SlowMigrationDdlRule; not part of the package's API.
 */
final readonly class ArrayDefinitionReader
{
    public function __construct(
        private PropertyArraySource $source,
    ) {}

    /**
     * Null when the list cannot be read. An empty list is a different answer: a map
     * that is statically empty runs no iteration, so there is nothing to check and
     * nothing to warn about.
     *
     * @return list<Closure|ArrowFunction>|null
     */
    public function closuresIteratedInto(Class_ $class, StaticCall $call, Expr $definition): ?array
    {
        if (! $definition instanceof Variable || ! is_string($definition->name)) {
            return null;
        }

        $loop = $this->loopBinding($class, $call, $definition->name);

        if (! $loop instanceof Foreach_) {
            return null;
        }

        $array = $this->source->literalFor($class, $loop->expr);

        if (! $array instanceof Array_) {
            return null;
        }

        $closures = [];

        foreach ($array->items as $item) {
            if (! $item->value instanceof Closure && ! $item->value instanceof ArrowFunction) {
                return null;
            }

            $closures[] = $item->value;
        }

        return $closures;
    }

    /**
     * The loop body assigning to its own value variable replaces the binding, so the
     * closure reaching the call is no longer the one the array holds.
     */
    private function rebinds(Foreach_ $loop, string $variable): bool
    {
        foreach ((new NodeFinder())->findInstanceOf($loop, Assign::class) as $assign) {
            if ($assign->var instanceof Variable && $assign->var->name === $variable) {
                return true;
            }
        }

        return false;
    }

    /**
     * The innermost loop binding the variable, since nested loops may reuse the name
     * and the value at the call comes from the nearest one.
     */
    private function loopBinding(Class_ $class, StaticCall $call, string $variable): ?Foreach_
    {
        $binding = null;

        foreach ($this->loopsBinding($class, $variable) as $loop) {
            if ($this->contains($loop, $call) && ($binding === null || $loop->getStartLine() > $binding->getStartLine())) {
                $binding = $loop;
            }
        }

        if (! $binding instanceof Foreach_) {
            return null;
        }

        return $this->rebinds($binding, $variable) ? null : $binding;
    }

    /**
     * @return list<Foreach_>
     */
    private function loopsBinding(Class_ $class, string $variable): array
    {
        $loops = [];

        foreach ((new NodeFinder())->findInstanceOf($class, Foreach_::class) as $loop) {
            if ($loop->valueVar instanceof Variable && $loop->valueVar->name === $variable) {
                $loops[] = $loop;
            }
        }

        return $loops;
    }

    private function contains(Foreach_ $loop, StaticCall $call): bool
    {
        return in_array($call, (new NodeFinder())->findInstanceOf($loop, StaticCall::class), true);
    }
}
