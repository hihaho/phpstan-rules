<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Answers whether a call takes one of its arguments by reference.
 *
 * Functions are looked up through PHPStan's reflection, so `array_push()` and
 * `sort()` come back by reference while `count()` and `array_keys()` do not, with no
 * list kept by hand. Methods on `$this` are read from the class being analysed.
 * Anything else — a dynamic callee, a method on another object — is reported as by
 * reference, since a parameter this cannot see may well be one.
 *
 * @internal collaborator of SlowMigrationDdlRule; not part of the package's API.
 */
final readonly class ByReferenceArguments
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function takes(Class_ $class, CallLike $call, Arg $argument, int $position): bool
    {
        if ($call instanceof FuncCall && $call->name instanceof Name) {
            return $this->functionTakes($call->name, $argument, $position);
        }

        if ($call instanceof MethodCall && $this->isOwnMethod($call)) {
            return $this->methodTakes($class, $call, $argument, $position);
        }

        return true;
    }

    private function functionTakes(Name $name, Arg $argument, int $position): bool
    {
        if (! $this->reflectionProvider->hasFunction($name, null)) {
            return true;
        }

        $parameters = $this->reflectionProvider->getFunction($name, null)->getVariants()[0]->getParameters();

        foreach ($parameters as $index => $parameter) {
            if ($this->matches($argument, $position, $index, $parameter->getName())) {
                return $parameter->passedByReference()->yes();
            }
        }

        return true;
    }

    private function methodTakes(Class_ $class, MethodCall $call, Arg $argument, int $position): bool
    {
        $name = $call->name instanceof Identifier ? $call->name->toString() : null;

        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() !== $name) {
                continue;
            }

            return $this->parameterTakes($method->params, $argument, $position);
        }

        return true;
    }

    /**
     * @param  array<Param>  $parameters
     */
    private function parameterTakes(array $parameters, Arg $argument, int $position): bool
    {
        foreach ($parameters as $index => $parameter) {
            if (! $parameter->var instanceof Variable || ! is_string($parameter->var->name)) {
                continue;
            }

            if ($this->matches($argument, $position, $index, $parameter->var->name)) {
                return $parameter->byRef;
            }
        }

        return true;
    }

    private function matches(Arg $argument, int $position, int $index, string $parameter): bool
    {
        return $argument->name instanceof Identifier
            ? $argument->name->toString() === $parameter
            : $index === $position;
    }

    private function isOwnMethod(MethodCall $call): bool
    {
        return $call->var instanceof Variable && $call->var->name === 'this';
    }
}
