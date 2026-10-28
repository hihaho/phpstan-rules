<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Exceptions;

use Override;
use PhpParser\Node;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

/**
 * Marks every catch that sits inside the body of a catch which already handles
 * its exception. PHPStan gives a rule no access to a node's ancestors, so
 * SwallowedExceptionRule reads this attribute instead.
 *
 * A typical case is a fallback that itself may fail, inside a catch that has
 * already reported the original failure: swallowing the fallback's exception
 * hides nothing the outer catch did not record.
 *
 * Runs on leave: PHPStan's NameResolver shares this traversal, so the class
 * names in a catch body (an aliased `Response::deny()`) are only resolved once
 * the body has been traversed.
 */
final class NestedCatchVisitor extends NodeVisitorAbstract
{
    public const string ATTRIBUTE = 'hihahoInsideHandlingCatch';

    private readonly NodeFinder $nodeFinder;

    public function __construct(
        private readonly CatchBodyInspector $inspector,
    ) {
        $this->nodeFinder = new NodeFinder();
    }

    #[Override]
    public function leaveNode(Node $node): null
    {
        if (! $node instanceof Catch_ || ! $this->inspector->handles($node)) {
            return null;
        }

        foreach ($this->nodeFinder->findInstanceOf($node->stmts, Catch_::class) as $nested) {
            $nested->setAttribute(self::ATTRIBUTE, true);
        }

        return null;
    }
}
