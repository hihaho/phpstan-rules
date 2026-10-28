<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Exceptions;

use Override;
use PhpParser\Node;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\NodeVisitorAbstract;

/**
 * Marks every catch that sits inside the body of a catch which already handles
 * its exception. PHPStan gives a rule no access to a node's ancestors, so
 * SwallowedExceptionRule reads this attribute instead.
 *
 * A typical case is a fallback that itself may fail, inside a catch that has
 * already reported the original failure: swallowing the fallback's exception
 * hides nothing the outer catch did not record.
 */
final class NestedCatchVisitor extends NodeVisitorAbstract
{
    public const string ATTRIBUTE = 'hihahoInsideHandlingCatch';

    /** @var list<bool> */
    private array $handlingStack = [];

    public function __construct(
        private readonly CatchBodyInspector $inspector,
    ) {}

    /**
     * @param  Node[]  $nodes
     */
    #[Override]
    public function beforeTraverse(array $nodes): null
    {
        $this->handlingStack = [];

        return null;
    }

    #[Override]
    public function enterNode(Node $node): null
    {
        if (! $node instanceof Catch_) {
            return null;
        }

        if (in_array(true, $this->handlingStack, true)) {
            $node->setAttribute(self::ATTRIBUTE, true);
        }

        $this->handlingStack[] = $this->inspector->handles($node);

        return null;
    }

    #[Override]
    public function leaveNode(Node $node): null
    {
        if ($node instanceof Catch_) {
            array_pop($this->handlingStack);
        }

        return null;
    }
}
