<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Html;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * Marks the closures whose return value a Nova field renders as raw HTML: the
 * resolve callback of `Field::make($name, $attribute, $callback)` and the
 * callbacks of `->displayUsing()` / `->resolveUsing()`, on a chain that also
 * calls `->asHtml()` (before or after them).
 *
 * The rule judges each return with the closure's own scope, which is only
 * available on the closure node itself. This visitor carries the "is a sink"
 * fact there, tagged with the class the chain starts from, so the rule can
 * check that class really is a Nova field.
 */
final class HtmlFieldClosureVisitor extends NodeVisitorAbstract
{
    public const string ATTRIBUTE = 'hihahoHtmlFieldClass';

    private const array CALLBACK_METHODS = [
        'displayusing' => true,
        'resolveusing' => true,
    ];

    /**
     * Runs on leave: PHPStan's NameResolver shares this traversal, so the class
     * of the chain's root call is only resolved once its subtree is done.
     */
    #[Override]
    public function leaveNode(Node $node): null
    {
        if (! $node instanceof MethodCall || ! $node->name instanceof Identifier) {
            return null;
        }

        $name = $node->name->toLowerString();

        if ($name === 'ashtml') {
            $this->markChain($node->var);
        } elseif (isset(self::CALLBACK_METHODS[$name]) && $this->chainCallsAsHtml($node->var)) {
            $this->markCallback($node, 0, $this->rootClass($node->var));
        }

        return null;
    }

    private function markChain(Expr $expr): void
    {
        $rootClass = $this->rootClass($expr);

        while ($expr instanceof MethodCall) {
            if ($expr->name instanceof Identifier && isset(self::CALLBACK_METHODS[$expr->name->toLowerString()])) {
                $this->markCallback($expr, 0, $rootClass);
            }

            $expr = $expr->var;
        }

        if ($expr instanceof StaticCall && $expr->name instanceof Identifier && $expr->name->toLowerString() === 'make') {
            $this->markCallback($expr, 2, $rootClass);
        }
    }

    private function chainCallsAsHtml(Expr $expr): bool
    {
        while ($expr instanceof MethodCall) {
            if ($expr->name instanceof Identifier && $expr->name->toLowerString() === 'ashtml') {
                return true;
            }

            $expr = $expr->var;
        }

        return false;
    }

    private function rootClass(Expr $expr): ?string
    {
        while ($expr instanceof MethodCall) {
            $expr = $expr->var;
        }

        return $expr instanceof StaticCall && $expr->class instanceof Name ? $expr->class->toString() : null;
    }

    /**
     * Nova's `make()` takes the callback third, or second when the attribute
     * is omitted, so a closure in either position is marked.
     */
    private function markCallback(MethodCall|StaticCall $call, int $position, ?string $rootClass): void
    {
        if ($rootClass === null) {
            return;
        }

        $positions = $position === 2 ? [1, 2] : [$position];

        foreach ($call->getArgs() as $index => $arg) {
            if (! in_array($index, $positions, true)) {
                continue;
            }

            if ($arg->value instanceof Closure || $arg->value instanceof ArrowFunction) {
                $arg->value->setAttribute(self::ATTRIBUTE, $rootClass);
            }
        }
    }
}
