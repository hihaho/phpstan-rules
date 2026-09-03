<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\NodeFinder;

/**
 * Works out which closures a `Schema::table()` call actually runs.
 *
 * A migration that guards every statement so a run killed by the deploy timeout can
 * resume tends to factor the guard into a private helper, which leaves the call
 * holding a parameter rather than a literal closure:
 *
 *     $this->addIndex(self::LEARNER_ID_INDEX, fn (Blueprint $table) => $table->index(...));
 *
 *     private function addIndex(string $index, Closure $definition): void
 *     {
 *         if (Schema::hasIndex($this->table, $index)) {
 *             return;
 *         }
 *
 *         Schema::table($this->table, $definition);
 *     }
 *
 * The parameter is traced back to the helper's own call sites, so the closures found
 * are the ones that reach this call and no others. A migration altering two tables
 * through two helpers keeps them apart.
 */
final readonly class BlueprintDefinitionResolver
{
    /**
     * The closures a call runs, or an empty list when they cannot be read — which the
     * rule reports rather than passes over.
     *
     * @return list<Closure|ArrowFunction>
     */
    public function forCall(Class_ $class, StaticCall $call): array
    {
        $argument = $call->args[1] ?? null;

        if (! $argument instanceof Arg) {
            return [];
        }

        if ($argument->value instanceof Closure || $argument->value instanceof ArrowFunction) {
            return [$argument->value];
        }

        if (! $argument->value instanceof Variable || ! is_string($argument->value->name)) {
            return [];
        }

        return $this->tracedToCallSites($class, $call, $argument->value->name);
    }

    /**
     * @return list<Closure|ArrowFunction>
     */
    private function tracedToCallSites(Class_ $class, StaticCall $call, string $variable): array
    {
        $method = $this->enclosingMethod($class, $call);

        if (! $method instanceof ClassMethod) {
            return [];
        }

        $position = $this->parameterPosition($method, $variable);

        if ($position === null) {
            return [];
        }

        return $this->closuresPassedTo($class, $method->name->toString(), $position, $variable);
    }

    private function enclosingMethod(Class_ $class, StaticCall $call): ?ClassMethod
    {
        $finder = new NodeFinder();

        foreach ($class->getMethods() as $method) {
            foreach ($finder->findInstanceOf($method, StaticCall::class) as $candidate) {
                if ($candidate === $call) {
                    return $method;
                }
            }
        }

        return null;
    }

    private function parameterPosition(ClassMethod $method, string $variable): ?int
    {
        foreach ($method->params as $position => $param) {
            if ($param->var instanceof Variable && $param->var->name === $variable) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @return list<Closure|ArrowFunction>
     */
    private function closuresPassedTo(Class_ $class, string $method, int $position, string $parameter): array
    {
        $closures = [];

        foreach ((new NodeFinder())->findInstanceOf($class, MethodCall::class) as $call) {
            if (! $call->var instanceof Variable || $call->var->name !== 'this') {
                continue;
            }

            if (! $call->name instanceof Identifier || $call->name->toString() !== $method) {
                continue;
            }

            $argument = $this->argumentFor($call->args, $position, $parameter);

            if ($argument instanceof Closure || $argument instanceof ArrowFunction) {
                $closures[] = $argument;
            }
        }

        return $closures;
    }

    /**
     * A named argument is matched by name, since the AST holds arguments in call-site
     * order and a named call may reorder them. Positional arguments are counted among
     * themselves, so a named argument earlier in the list does not shift them.
     *
     * @param  array<Arg|VariadicPlaceholder>  $arguments
     */
    private function argumentFor(array $arguments, int $position, string $parameter): ?Expr
    {
        $unnamed = 0;

        foreach ($arguments as $argument) {
            if (! $argument instanceof Arg) {
                continue;
            }

            if ($argument->name instanceof Identifier) {
                if ($argument->name->toString() === $parameter) {
                    return $argument->value;
                }

                continue;
            }

            if ($unnamed === $position) {
                return $argument->value;
            }

            ++$unnamed;
        }

        return null;
    }
}
