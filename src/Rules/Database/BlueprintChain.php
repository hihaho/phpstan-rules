<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;

/**
 * One `$table->...()->...();` statement inside a `Schema::table()` closure, read as
 * the list of method names it chains. Reading the whole chain rather than a line is
 * what makes a modifier split across lines count:
 *
 *     $table->string('foo', 255)
 *         ->nullable()
 *         ->instant();
 */
final readonly class BlueprintChain
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    /**
     * Every chain in the closure whose root is the Blueprint the closure was handed,
     * outermost call first. Chains are collected from the whole closure body rather
     * than from top-level expression statements, so a chain assigned to a variable
     * (`$column = $table->string('x');`) or written inside a conditional counts. A
     * chain rooted anywhere else — a query builder, another object — is left alone.
     *
     * @return list<MethodCall>
     */
    public function rootedChains(Closure|ArrowFunction $closure): array
    {
        $blueprint = $closure->params[0]->var ?? null;

        if (! $blueprint instanceof Variable || ! is_string($blueprint->name)) {
            return [];
        }

        $calls = (new NodeFinder())->findInstanceOf($closure, MethodCall::class);
        $inner = [];

        foreach ($calls as $call) {
            if ($call->var instanceof MethodCall) {
                $inner[spl_object_id($call->var)] = true;
            }
        }

        $chains = [];

        foreach ($calls as $call) {
            if (! isset($inner[spl_object_id($call)]) && $this->isRootedIn($call, $blueprint->name)) {
                $chains[] = $call;
            }
        }

        return $chains;
    }

    /**
     * Every Blueprint-typed closure declared anywhere in the class, wherever it is
     * eventually handed to `Schema::table()`. A migration that guards each statement
     * so a run killed by the deploy timeout can resume tends to factor the guard into
     * a helper:
     *
     *     $this->addIndex(self::LEARNER_ID_INDEX, fn (Blueprint $table) => $table->index(...));
     *
     *     private function addIndex(string $index, Closure $definition): void
     *     {
     *         Schema::table($this->table, $definition);
     *     }
     *
     * There is no literal closure at the `Schema::table()` call to read, so the
     * closures are collected from the class instead. In a self-contained migration a
     * Blueprint parameter has exactly one purpose, which makes the type hint a
     * reliable marker.
     *
     * @return list<Closure|ArrowFunction>
     */
    public function blueprintClosures(Class_ $class): array
    {
        $finder = new NodeFinder();

        /** @var list<Closure|ArrowFunction> $functions */
        $functions = [
            ...$finder->findInstanceOf($class, Closure::class),
            ...$finder->findInstanceOf($class, ArrowFunction::class),
        ];

        return array_values(array_filter(
            $functions,
            static function (Closure|ArrowFunction $function): bool {
                $type = $function->params[0]->type ?? null;

                return $type instanceof Name && $type->getLast() === 'Blueprint';
            },
        ));
    }

    private function isRootedIn(MethodCall $call, string $variable): bool
    {
        $expr = $call;

        while ($expr instanceof MethodCall) {
            $expr = $expr->var;
        }

        return $expr instanceof Variable && $expr->name === $variable;
    }

    /**
     * @return list<string> outermost call first, root call last
     */
    public function methodNames(Expr $expr): array
    {
        $names = [];

        while ($expr instanceof MethodCall) {
            if ($expr->name instanceof Identifier) {
                $names[] = $expr->name->toString();
            }

            $expr = $expr->var;
        }

        return $names;
    }

    /**
     * @param  list<string>  $names
     */
    public function addsOrDropsColumn(array $names): bool
    {
        foreach ($names as $name) {
            if ($name === 'dropColumn' || $this->definesColumn($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A Blueprint method is column work when it hands back a ColumnDefinition —
     * which `foreignId()` does through its ForeignIdColumnDefinition subclass. The
     * `drop*` index family returns a plain Fluent and is deliberately excluded: it
     * runs in `down()`, which a deploy never executes.
     */
    private function definesColumn(string $name): bool
    {
        if (! $this->reflectionProvider->hasClass(Blueprint::class)) {
            return false;
        }

        $blueprint = $this->reflectionProvider->getClass(Blueprint::class);

        if (! $blueprint->hasNativeMethod($name)) {
            return false;
        }

        $returnType = $blueprint->getNativeMethod($name)->getVariants()[0]->getReturnType();

        return (new ObjectType(ColumnDefinition::class))->isSuperTypeOf($returnType)->yes();
    }
}
