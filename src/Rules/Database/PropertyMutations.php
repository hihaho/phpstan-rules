<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;

/**
 * Decides whether anything in the class can change a property's array after it is
 * assigned.
 *
 * Reading the property cannot: PHP arrays are values, so `count($this->columns)` or
 * an `array_keys()` in `down()` hands out a copy. Only a construct that aliases the
 * property or writes through it can, whether it names the array or one of its
 * elements: a reference bind, a by-reference `foreach`, `unset()`, `++`/`--`, an
 * argument the callee takes by reference, or a `function &get()` handing the property
 * out for a caller to write through.
 *
 * @internal collaborator of SlowMigrationDdlRule; not part of the package's API.
 */
final readonly class PropertyMutations
{
    public function __construct(
        private ByReferenceArguments $byReference,
    ) {}

    public function canChange(Class_ $class, string $property): bool
    {
        foreach ($this->mutatingExpressions($class) as $expression) {
            if ($this->isProperty($expression, $property)) {
                return true;
            }
        }

        return $this->passedByReference($class, $property);
    }

    /**
     * Every expression whose evaluation can write to what it names.
     *
     * @return list<Expr>
     */
    private function mutatingExpressions(Class_ $class): array
    {
        return [
            ...$this->aliased($class),
            ...$this->stepped($class),
            ...$this->discarded($class),
            ...$this->handedOut($class),
        ];
    }

    /**
     * @return list<Expr>
     */
    private function aliased(Class_ $class): array
    {
        $expressions = [];

        foreach ($this->find($class, AssignRef::class) as $bind) {
            $expressions[] = $bind->expr;
        }

        foreach ($this->find($class, Foreach_::class) as $loop) {
            if ($loop->byRef) {
                $expressions[] = $loop->expr;
            }
        }

        return $expressions;
    }

    /**
     * @return list<Expr>
     */
    private function stepped(Class_ $class): array
    {
        $steps = [
            ...$this->find($class, PreInc::class),
            ...$this->find($class, PostInc::class),
            ...$this->find($class, PreDec::class),
            ...$this->find($class, PostDec::class),
        ];

        return array_map(static fn (PreInc|PostInc|PreDec|PostDec $step): Expr => $step->var, $steps);
    }

    /**
     * @return list<Expr>
     */
    private function discarded(Class_ $class): array
    {
        $expressions = [];

        foreach ($this->find($class, Unset_::class) as $unset) {
            $expressions = [...$expressions, ...array_values($unset->vars)];
        }

        return $expressions;
    }

    /**
     * A `function &definitions()` returning the property lets its caller write through
     * the reference it hands back.
     *
     * @return list<Expr>
     */
    private function handedOut(Class_ $class): array
    {
        $expressions = [];

        foreach ($class->getMethods() as $method) {
            foreach ($method->byRef ? $this->find($method, Return_::class) : [] as $return) {
                if ($return->expr instanceof Expr) {
                    $expressions[] = $return->expr;
                }
            }
        }

        return $expressions;
    }

    private function passedByReference(Class_ $class, string $property): bool
    {
        foreach ($this->find($class, CallLike::class) as $call) {
            foreach ($call->getArgs() as $position => $argument) {
                if ($this->isProperty($argument->value, $property) && $this->byReference->takes($class, $call, $argument, $position)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @template TNode of Node
     *
     * @param  class-string<TNode>  $type
     * @return list<TNode>
     */
    private function find(Node $node, string $type): array
    {
        /** @var list<TNode> */
        return (new NodeFinder())->findInstanceOf($node, $type);
    }

    /**
     * An element of the array counts as the property: replacing one entry replaces a
     * definition the literal still shows.
     */
    private function isProperty(Expr $expr, string $property): bool
    {
        while ($expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        return $expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === $property;
    }
}
