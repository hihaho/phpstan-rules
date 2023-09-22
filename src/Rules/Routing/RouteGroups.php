<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Routing;

use Hihaho\PhpstanRules\Traits\HasUrlTip;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @see https://guidelines.hihaho.com/laravel.html#route-groups
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\StaticCall>
 */
class RouteGroups implements Rule
{
    use HasUrlTip;

    public function getNodeType(): string
    {
        return \PhpParser\Node\Expr\StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $isRouteFile = Str::of($scope->getFile())
            ->contains(DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR);

        if (! $isRouteFile) {
            return [];
        }

        if ($node->class->toString() !== Route::class) {
            return [];
        }

        if ($node->name->toString() !== 'group') {
            return [];
        }

        /** @var \PhpParser\Node\Expr\Array_ */
        $routePath = $node->args[0]?->value;
        if ($routePath?->getType() !== 'Expr_Array') {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Route group options should be defined using methods.'
            )
                ->tip($this->docsTip('https://guidelines.hihaho.com/laravel.html#route-groups'))
                ->build(),
        ];
    }
}
