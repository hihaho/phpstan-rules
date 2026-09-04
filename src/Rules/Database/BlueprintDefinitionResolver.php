<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * Reads a `Schema::table()` call as the invocations it actually performs: which table
 * each one alters, and which closures it runs against it.
 *
 * A migration that guards every statement so a run killed by the deploy hook can
 * resume rarely writes the call literally. The guard is factored into a helper, and
 * the table, the definition, or both arrive as parameters:
 *
 *     $this->addColumn('video_sessions', 'foo', fn (Blueprint $table) => $table->string('foo'));
 *
 *     private function addColumn(string $table, string $column, Closure $definition): void
 *     {
 *         if (Schema::hasColumn($table, $column)) {
 *             return;
 *         }
 *
 *         Schema::table($table, $definition);
 *     }
 *
 * Both are traced back to the helper's own call sites and paired there, so a helper
 * used for two tables reports each against the table it was actually given.
 *
 * @internal collaborator of SlowMigrationDdlRule; not part of the package's API.
 */
final readonly class BlueprintDefinitionResolver
{
    public function __construct(
        private ArrayDefinitionReader $arrays,
        private HelperCallSites $helpers,
    ) {}

    /**
     * @return list<array{Expr, list<Closure|ArrowFunction>|null}> the table expression
     *                                                             and the closures run
     *                                                             against it, null when
     *                                                             they cannot be read
     */
    public function invocations(Class_ $class, StaticCall $call): array
    {
        $table = $call->args[0] ?? null;
        $definition = $call->args[1] ?? null;

        if (! $table instanceof Arg || ! $definition instanceof Arg) {
            return [];
        }

        $method = $this->helpers->enclosingMethod($class, $call);

        $local = $this->closuresAt($class, $call, $definition->value);

        if ($method instanceof ClassMethod) {
            $traced = $this->fromCallSites($class, $method, $table->value, $definition->value, $local);

            if ($traced !== null) {
                return $traced;
            }
        }

        return [[$table->value, $local]];
    }

    /**
     * Null when neither argument is a parameter of the enclosing method — there is
     * nothing to trace and the call speaks for itself.
     *
     * @param  list<Closure|ArrowFunction>|null  $local  the definitions the helper
     *                                                   itself holds, used when only
     *                                                   the table is a parameter
     * @return list<array{Expr, list<Closure|ArrowFunction>|null}>|null
     */
    private function fromCallSites(Class_ $class, ClassMethod $method, Expr $table, Expr $definition, ?array $local): ?array
    {
        $tableParameter = $this->helpers->parameterOf($method, $table);
        $definitionParameter = $this->helpers->parameterOf($method, $definition);

        if ($tableParameter === null && $definitionParameter === null) {
            return null;
        }

        $invocations = [];

        foreach ($this->helpers->callsTo($class, $method->name->toString()) as $call) {
            $tableAt = $tableParameter === null
                ? $table
                : $this->helpers->argumentFor($call->args, $tableParameter[0], $tableParameter[1]) ?? $tableParameter[2];

            if (! $tableAt instanceof Expr) {
                continue;
            }

            $invocations[] = [$tableAt, $definitionParameter === null
                ? $local
                : $this->closureList($this->helpers->argumentFor($call->args, $definitionParameter[0], $definitionParameter[1]))];
        }

        // No call site to read means the helper is reached some other way; the call is
        // then no better understood than an untraced one, and must not fall silent.
        return $invocations === [] ? null : $invocations;
    }

    /**
     * The closures a call runs when nothing needs tracing: written at the call, or
     * held in an array the surrounding loop iterates.
     *
     * @return list<Closure|ArrowFunction>|null
     */
    private function closuresAt(Class_ $class, StaticCall $call, Expr $definition): ?array
    {
        if ($definition instanceof Closure || $definition instanceof ArrowFunction) {
            return [$definition];
        }

        return $this->arrays->closuresIteratedInto($class, $call, $definition);
    }

    /**
     * @return list<Closure|ArrowFunction>|null
     */
    private function closureList(?Expr $expr): ?array
    {
        return $expr instanceof Closure || $expr instanceof ArrowFunction ? [$expr] : null;
    }
}
