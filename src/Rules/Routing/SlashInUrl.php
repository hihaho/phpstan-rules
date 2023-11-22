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
 * @see https://guidelines.hihaho.com/laravel.html#slash-in-url
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\StaticCall>
 */
class SlashInUrl implements Rule
{
    use HasUrlTip;

    public function docs(): string
    {
        return 'https://guidelines.hihaho.com/laravel.html#slash-in-url';
    }

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

        if (! in_array($node->name->toString(), ['get', 'post', 'put', 'patch', 'delete', 'any', 'head'])) {
            return [];
        }

        /** @var \PhpParser\Node\Scalar\String_ */
        $routePath = $node->args[0]?->value;
        if ($routePath?->getType() !== 'Scalar_String') {
            return [];
        }

        if ($routePath->value === '/') {
            return [];
        }

        if ($routePath->value === '') {
            return [
                RuleErrorBuilder::message(
                    'A route URL should be / instead of an empty string.'
                )
                    ->tip($this->tip())
                    ->build(),
            ];
        }

        if (Str::startsWith($routePath->value, '/') || Str::endsWith($routePath->value, '/')) {
            return [
                RuleErrorBuilder::message(
                    'A route URL should not start or end with /.'
                )
                    ->tip($this->tip())
                    ->build(),
            ];
        }

        return [];
    }
}
