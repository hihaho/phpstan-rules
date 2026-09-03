<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Property;

/**
 * Reads string literals out of a single migration class without help from the type
 * engine. PHPStan infers `string` for `$this->table` when the property is typed
 * (`private string $table = 'video_sessions'`), and `non-falsy-string` for an
 * interpolated SQL statement — in both cases the value the rule needs is gone. The
 * literal survives on the AST, so it is read straight off the class declaration.
 *
 * Migrations are self-contained by guideline (string literals, no model constants),
 * so the declaring class is the whole lookup scope.
 */
final class MigrationTableNameResolver
{
    /** @var array<string, string> */
    private array $properties = [];

    /** @var array<string, string> */
    private array $constants = [];

    public function __construct(Class_ $class)
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Property) {
                $this->collectProperties($stmt);
            }

            if ($stmt instanceof ClassConst) {
                $this->collectConstants($stmt);
            }
        }
    }

    /**
     * The literal an expression stands for, or null when it cannot be read
     * statically. Null never produces an error — an unresolvable table name is
     * reported as safe rather than guessed at.
     */
    public function resolve(Expr $expr): ?string
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if ($expr instanceof PropertyFetch) {
            return $this->resolvePropertyFetch($expr);
        }

        if ($expr instanceof ClassConstFetch && $expr->name instanceof Identifier) {
            return $this->constants[$expr->name->toString()] ?? null;
        }

        return null;
    }

    /**
     * The text of a string expression with every resolvable interpolation
     * substituted, so `"ALTER TABLE `{$this->table}`"` becomes the statement MySQL
     * actually receives. Unresolvable parts collapse to an empty string: they cannot
     * make a statement match a table name it does not name.
     */
    public function flatten(Expr $expr): ?string
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if ($expr instanceof Concat) {
            return ($this->flatten($expr->left) ?? '') . ($this->flatten($expr->right) ?? '');
        }

        if ($expr instanceof InterpolatedString) {
            return $this->flattenParts($expr);
        }

        return null;
    }

    private function flattenParts(InterpolatedString $expr): string
    {
        $text = '';

        foreach ($expr->parts as $part) {
            if ($part instanceof InterpolatedStringPart) {
                $text .= $part->value;

                continue;
            }

            $text .= $this->resolve($part) ?? '';
        }

        return $text;
    }

    private function resolvePropertyFetch(PropertyFetch $expr): ?string
    {
        if (! $expr->var instanceof Variable || $expr->var->name !== 'this') {
            return null;
        }

        if (! $expr->name instanceof Identifier) {
            return null;
        }

        return $this->properties[$expr->name->toString()] ?? null;
    }

    private function collectProperties(Property $property): void
    {
        foreach ($property->props as $prop) {
            if ($prop->default instanceof String_) {
                $this->properties[$prop->name->toString()] = $prop->default->value;
            }
        }
    }

    private function collectConstants(ClassConst $constant): void
    {
        foreach ($constant->consts as $const) {
            if ($const->value instanceof String_) {
                $this->constants[$const->name->toString()] = $const->value->value;
            }
        }
    }
}
