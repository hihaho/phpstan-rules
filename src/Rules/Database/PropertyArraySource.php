<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

/**
 * Resolves an expression to the array literal it provably holds.
 *
 * A property qualifies only when one literal fills it and nothing else in the class
 * touches it. Anything looser — a second write, a compound write, a mutating call, a
 * read this class does not model — means the array at the point of use is not
 * provably the literal, and the caller is told so rather than shown a partial answer.
 *
 * @internal collaborator of SlowMigrationDdlRule; not part of the package's API.
 */
final class PropertyArraySource
{
    public function literalFor(Class_ $class, Expr $expr): ?Array_
    {
        if ($expr instanceof Array_) {
            return $expr;
        }

        $property = $this->propertyName($expr);

        if ($property === null) {
            return null;
        }

        $sources = [
            ...$this->declaredDefaults($class, $property),
            ...$this->assignedValues($class, $property),
        ];

        if (count($sources) !== 1 || ! $sources[0] instanceof Array_) {
            return null;
        }

        return $this->escapes($class, $property, $expr) ? null : $sources[0];
    }

    /**
     * True when the property is touched anywhere other than the loop that reads it and
     * the single write that fills it — passed to `array_push()`, handed to something
     * taking it by reference, or read in a way this reader does not model. The list at
     * the loop is then not provably the literal, so it is not read at all.
     */
    private function escapes(Class_ $class, string $property, Expr $iterated): bool
    {
        foreach ((new NodeFinder())->findInstanceOf($class, PropertyFetch::class) as $fetch) {
            if ($this->propertyName($fetch) !== $property || $fetch === $iterated) {
                continue;
            }

            if (! $this->isWriteTarget($class, $fetch)) {
                return true;
            }
        }

        return false;
    }

    private function isWriteTarget(Class_ $class, PropertyFetch $fetch): bool
    {
        foreach ($this->writes($class) as $write) {
            $target = $write->var instanceof ArrayDimFetch ? $write->var->var : $write->var;

            if ($target === $fetch) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Expr>
     */
    private function declaredDefaults(Class_ $class, string $property): array
    {
        $defaults = [];

        foreach ($class->getProperties() as $declaration) {
            foreach ($declaration->props as $prop) {
                if ($prop->name->toString() === $property && $prop->default instanceof Expr) {
                    $defaults[] = $prop->default;
                }
            }
        }

        return $defaults;
    }

    /**
     * Every write to the property counts: an indexed one (`$this->columns['x'] = …`),
     * a compound one (`$this->columns += […]`) and a reference bind alike. Two writes
     * mean the array reaching the loop is not the array read here.
     *
     * @return list<Expr>
     */
    private function assignedValues(Class_ $class, string $property): array
    {
        $values = [];

        foreach ($this->writes($class) as $write) {
            $target = $write->var instanceof ArrayDimFetch ? $write->var->var : $write->var;

            if ($this->propertyName($target) !== $property) {
                continue;
            }

            // A compound write adds to whatever the property already held, which may
            // come from a parent or a path this reader never sees. Recording the node
            // itself rather than its right-hand side keeps it from passing as the
            // property's whole value.
            $values[] = $write instanceof Assign ? $write->expr : $write;
        }

        return $values;
    }

    /**
     * @return list<Assign|AssignOp|AssignRef>
     */
    private function writes(Class_ $class): array
    {
        $finder = new NodeFinder();

        /** @var list<Assign|AssignOp|AssignRef> $writes */
        $writes = [
            ...array_values($finder->findInstanceOf($class, Assign::class)),
            ...array_values($finder->findInstanceOf($class, AssignOp::class)),
            ...array_values($finder->findInstanceOf($class, AssignRef::class)),
        ];

        return $writes;
    }

    private function propertyName(Expr $expr): ?string
    {
        if (! $expr instanceof PropertyFetch || ! $expr->var instanceof Variable || $expr->var->name !== 'this') {
            return null;
        }

        return $expr->name instanceof Identifier ? $expr->name->toString() : null;
    }
}
