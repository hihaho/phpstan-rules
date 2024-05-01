<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\StaticCall>
 */
class OnlyAllowFacadeAliasInBlade implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Node\Name) {
            return [];
        }

        // A facade alias is not relative to the current namespace, neither is it `self`, `parent` or `static`.
        if ($node->class->isRelative() || $node->class->isSpecialClassName()) {
            return [];
        }

        // The class consists of multiple parts, so it's (likely) not a facade alias.
        if (count($node->class->getParts()) > 1) {
            return [];
        }

        // Ignore calls in Blade files.
        if (Str::endsWith($scope->getFileDescription(), '.blade.php')) {
            return [];
        }

        $reflected = new \ReflectionClass($node->class->toCodeString());

        if ($reflected->isSubclassOf(Facade::class)) {
            return [
                RuleErrorBuilder::message(
                    'Disallowed usage of `' . $node->class->toString() . '` facade alias, use `' . $reflected->getName() . '`. A facade alias can only be used in Blade.'
                )->build(),
            ];
        }

        return [];
    }
}
