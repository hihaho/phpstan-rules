<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Routing;

use Hihaho\PhpstanRules\Traits\HasUrlTip;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @see https://guidelines.hihaho.com/laravel.html#route-groups
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\StaticCall>
 */
class RouteGroups implements Rule
{
    use HasUrlTip;

    public function docs(): string
    {
        return 'https://guidelines.hihaho.com/laravel.html#route-groups';
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $isRouteFile = Str::of($scope->getFile())
            ->contains(DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR);

        if (! $isRouteFile) {
            return [];
        }

        if (! $node->class instanceof Node\Name) {
            return [];
        }

        if ($node->class->toString() !== Route::class) {
            return [];
        }

        if (! $node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toString() !== 'group') {
            return [];
        }

        $arg = $node->args[0];

        if (! $arg instanceof Arg) {
            return [];
        }

        /** @var \PhpParser\Node\Expr\Array_ $routePath */
        $routePath = $arg->value;

        if ($routePath->getType() !== 'Expr_Array') {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Route group options should be defined using methods.'
            )
                ->tip($this->tip())
                ->identifier('hihaho.routing.routeGroups')
                ->build(),
        ];
    }
}
