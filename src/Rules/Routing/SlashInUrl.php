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

        if (! in_array($node->name->toString(), ['get', 'post', 'put', 'patch', 'delete', 'any', 'head'], true)) {
            return [];
        }

        $arg = $node->args[0];

        if (! $arg instanceof Arg) {
            return [];
        }

        /** @var \PhpParser\Node\Scalar\String_ $routePath */
        $routePath = $arg->value;

        if ($routePath->getType() !== 'Scalar_String') {
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
                    ->identifier('hihaho.routing.noEmptyPath')
                    ->build(),
            ];
        }

        if (Str::startsWith($routePath->value, '/') || Str::endsWith($routePath->value, '/')) {
            return [
                RuleErrorBuilder::message(
                    'A route URL should not start or end with /.'
                )
                    ->tip($this->tip())
                    ->identifier('hihaho.routing.noLeadingOrTrailingSlashInUrl')
                    ->build(),
            ];
        }

        return [];
    }
}
