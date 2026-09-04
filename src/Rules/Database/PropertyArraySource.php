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
 * A property qualifies when exactly one literal fills it and nothing in the class can
 * change it afterwards. A second write, a compound write, or anything that can mutate
 * it through a reference disqualifies it: the array at the point of use would then not
 * provably be the literal. Reading it elsewhere is fine, since a read hands out a copy.
 *
 * @internal collaborator of SlowMigrationDdlRule; not part of the package's API.
 */
final readonly class PropertyArraySource
{
    public function __construct(
        private PropertyMutations $mutations,
    ) {}

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

        return $this->mutations->canChange($class, $property) ? null : $sources[0];
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
            $target = $write->var;

            while ($target instanceof ArrayDimFetch) {
                $target = $target->var;
            }

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
