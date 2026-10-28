<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Exceptions;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Decides, from the syntax of a catch body alone, whether the caught exception
 * reaches anything that records it: a rethrow, a report/log/abort call, a job
 * failure, a typed denial, or any read of the caught variable (which hands it to
 * a helper that may report it).
 *
 * A nested catch is not searched. Its handling covers only the exception *it*
 * catches; when the fallback inside the outer catch succeeds, the outer
 * exception is still gone.
 */
final class CatchBodyInspector
{
    private const array REPORTING_FUNCTIONS = [
        'report' => true,
        'report_if' => true,
        'report_unless' => true,
        'logger' => true,
    ];

    private const array LOG_CLASSES = [
        'illuminate\support\facades\log' => true,
        'log' => true,
    ];

    /**
     * `failed()` is deliberately absent: a job that calls its own `failed()` hook
     * still finishes as a success, so the queue never records the failure.
     */
    private const array FAILING_METHODS = [
        'fail' => true,
        'release' => true,
    ];

    private const string AUTHORIZATION_RESPONSE = 'illuminate\auth\access\response';

    public function handles(Catch_ $catch): bool
    {
        $caughtName = $catch->var instanceof Variable && is_string($catch->var->name) ? $catch->var->name : null;

        $visitor = new class ($this, $caughtName) extends NodeVisitorAbstract {
            public bool $handled = false;

            /** @var array<int, true> */
            private array $assignTargets = [];

            public function __construct(
                private readonly CatchBodyInspector $inspector,
                private readonly ?string $caughtName,
            ) {}

            #[Override]
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Catch_) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof Assign) {
                    $this->assignTargets[spl_object_id($node->var)] = true;
                }

                if ($this->inspector->isHandlingNode($node) || $this->readsCaughtVariable($node)) {
                    $this->handled = true;

                    return NodeVisitor::STOP_TRAVERSAL;
                }

                return null;
            }

            private function readsCaughtVariable(Node $node): bool
            {
                return $this->caughtName !== null
                    && $node instanceof Variable
                    && $node->name === $this->caughtName
                    && ! isset($this->assignTargets[spl_object_id($node)]);
            }
        };

        $traverser = new NodeTraverser($visitor);
        $traverser->traverse($catch->stmts);

        return $visitor->handled;
    }

    /**
     * @internal used by the traversal in handles()
     */
    public function isHandlingNode(Node $node): bool
    {
        return $node instanceof Throw_
            || $this->isReportingFunctionCall($node)
            || $this->isLogFacadeCall($node)
            || $this->isFailingMethodCall($node)
            || $this->isValidationFailCall($node)
            || $this->returnsAuthorizationDenial($node);
    }

    private function isReportingFunctionCall(Node $node): bool
    {
        if (! $node instanceof FuncCall || ! $node->name instanceof Name) {
            return false;
        }

        $name = strtolower($node->name->getLast());

        return isset(self::REPORTING_FUNCTIONS[$name]) || str_starts_with($name, 'abort');
    }

    private function isLogFacadeCall(Node $node): bool
    {
        return $node instanceof StaticCall
            && $node->class instanceof Name
            && isset(self::LOG_CLASSES[$node->class->toLowerString()]);
    }

    private function isFailingMethodCall(Node $node): bool
    {
        return ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
            && $node->name instanceof Identifier
            && isset(self::FAILING_METHODS[$node->name->toLowerString()]);
    }

    /**
     * The `$fail` closure a validation rule receives records the failure as a
     * validation error, which is how the rule is meant to report it.
     */
    private function isValidationFailCall(Node $node): bool
    {
        return $node instanceof FuncCall
            && $node->name instanceof Variable
            && $node->name->name === 'fail';
    }

    private function returnsAuthorizationDenial(Node $node): bool
    {
        if (! $node instanceof Return_ || ! $node->expr instanceof StaticCall) {
            return false;
        }

        $call = $node->expr;

        return $call->class instanceof Name
            && $call->class->toLowerString() === self::AUTHORIZATION_RESPONSE
            && $call->name instanceof Identifier
            && str_starts_with($call->name->toLowerString(), 'deny');
    }
}
