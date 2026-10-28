<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Html;

use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Accessory\AccessoryLiteralStringType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Decides whether an expression that ends up in raw HTML can carry markup the
 * author did not write. Concatenations, interpolations, ternaries and
 * `sprintf()` are split, and each part is judged on its own: one escaped part
 * does not make its neighbour safe.
 *
 * A part is safe when it is escaped (`e()`, `htmlspecialchars()`,
 * `htmlentities()`), already HTML (`Htmlable`, or `->toHtml()` on one), or of a
 * type that cannot hold markup: a literal string, a number, a bool, null or a
 * backed enum. Anything else, a plain `string` variable included, is unsafe.
 */
final class HtmlSafetyJudge
{
    private const array ESCAPING_FUNCTIONS = [
        'e' => true,
        'htmlspecialchars' => true,
        'htmlentities' => true,
    ];

    private const array FORMATTING_FUNCTIONS = [
        'sprintf' => true,
        'vsprintf' => true,
    ];

    private const array HTML_RENDERING_METHODS = [
        'tohtml' => true,
        'render' => true,
    ];

    private ?Type $safeType = null;

    public function isSafe(Expr $expr, Scope $scope): bool
    {
        $parts = $this->splitParts($expr);

        if ($parts !== null) {
            foreach ($parts as $part) {
                if (! $this->isSafe($part, $scope)) {
                    return false;
                }
            }

            return true;
        }

        if ($this->isEscapingCall($expr) || $this->isHtmlRenderingCall($expr, $scope)) {
            return true;
        }

        return $this->isSafeType($scope->getType($expr));
    }

    /**
     * @return list<Expr>|null null when the expression is a single part
     */
    private function splitParts(Expr $expr): ?array
    {
        return match (true) {
            $expr instanceof Concat, $expr instanceof Coalesce => [$expr->left, $expr->right],
            $expr instanceof Ternary => [$expr->if ?? $expr->cond, $expr->else],
            $expr instanceof InterpolatedString => array_values(array_filter(
                $expr->parts,
                static fn (Expr|InterpolatedStringPart $part): bool => $part instanceof Expr,
            )),
            $expr instanceof Array_ => array_values(array_map(
                static fn (ArrayItem $item): Expr => $item->value,
                $expr->items,
            )),
            $this->isFormattingCall($expr) => $this->callArgumentValues($expr),
            default => null,
        };
    }

    private function isEscapingCall(Expr $expr): bool
    {
        return $expr instanceof FuncCall
            && $expr->name instanceof Name
            && isset(self::ESCAPING_FUNCTIONS[strtolower($expr->name->getLast())]);
    }

    private function isFormattingCall(Expr $expr): bool
    {
        return $expr instanceof FuncCall
            && $expr->name instanceof Name
            && isset(self::FORMATTING_FUNCTIONS[strtolower($expr->name->getLast())]);
    }

    /**
     * @return list<Expr>
     */
    private function callArgumentValues(Expr $expr): array
    {
        if (! $expr instanceof FuncCall) {
            return [];
        }

        $values = [];

        foreach ($expr->getArgs() as $arg) {
            $values[] = $arg->value;
        }

        return $values;
    }

    /**
     * `Htmlable::toHtml()` returns a plain string, but the contract promises
     * that string is HTML the object built, so it is as safe as the object.
     */
    private function isHtmlRenderingCall(Expr $expr, Scope $scope): bool
    {
        return $expr instanceof MethodCall
            && $expr->name instanceof Identifier
            && isset(self::HTML_RENDERING_METHODS[$expr->name->toLowerString()])
            && (new ObjectType(Htmlable::class))->isSuperTypeOf($scope->getType($expr->var))->yes();
    }

    private function isSafeType(Type $type): bool
    {
        if ($type->isArray()->yes()) {
            return $this->isSafeType($type->getIterableValueType());
        }

        return $this->safeType()->isSuperTypeOf($type)->yes();
    }

    private function safeType(): Type
    {
        return $this->safeType ??= TypeCombinator::union(
            new IntersectionType([new StringType(), new AccessoryLiteralStringType()]),
            new IntegerType(),
            new FloatType(),
            new BooleanType(),
            new NullType(),
            new ObjectType(BackedEnum::class),
            new ObjectType(Htmlable::class),
        );
    }
}
