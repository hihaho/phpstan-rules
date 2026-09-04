<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\NodeFinder;

/**
 * Looks up where a private helper is called and which argument fills a given
 * parameter, so a value that arrives at `Schema::table()` as a parameter can be read
 * at the call sites that supply it.
 *
 * @internal collaborator of SlowMigrationDdlRule; not part of the package's API.
 */
final class HelperCallSites
{
    /**
     * @return array{int, string, Expr|null}|null the parameter's position, name and
     *                                            default, the default standing in for
     *                                            an argument a call site omits
     */
    public function parameterOf(ClassMethod $method, Expr $expr): ?array
    {
        if (! $expr instanceof Variable || ! is_string($expr->name)) {
            return null;
        }

        foreach ($method->params as $position => $param) {
            if ($param->var instanceof Variable && $param->var->name === $expr->name) {
                return [$position, $expr->name, $param->default];
            }
        }

        return null;
    }

    public function enclosingMethod(Class_ $class, StaticCall $call): ?ClassMethod
    {
        $finder = new NodeFinder();

        foreach ($class->getMethods() as $method) {
            foreach ($finder->findInstanceOf($method, StaticCall::class) as $candidate) {
                if ($candidate === $call) {
                    return $method;
                }
            }
        }

        return null;
    }

    /**
     * @return list<MethodCall>
     */
    public function callsTo(Class_ $class, string $method): array
    {
        $calls = [];

        foreach ((new NodeFinder())->findInstanceOf($class, MethodCall::class) as $call) {
            if (! $call->var instanceof Variable || $call->var->name !== 'this') {
                continue;
            }

            if ($call->name instanceof Identifier && $call->name->toString() === $method) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /**
     * A named argument is matched by name, since the AST holds arguments in call-site
     * order and a named call may reorder them. Positional arguments are counted among
     * themselves, so a named argument earlier in the list does not shift them.
     *
     * @param  array<Arg|VariadicPlaceholder>  $arguments
     */
    public function argumentFor(array $arguments, int $position, string $parameter): ?Expr
    {
        $unnamed = 0;

        foreach ($arguments as $argument) {
            if (! $argument instanceof Arg) {
                continue;
            }

            if ($argument->name instanceof Identifier) {
                if ($argument->name->toString() === $parameter) {
                    return $argument->value;
                }

                continue;
            }

            if ($unnamed === $position) {
                return $argument->value;
            }

            ++$unnamed;
        }

        return null;
    }
}
