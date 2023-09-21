<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\NamingClasses;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @see https://guidelines.hihaho.com/laravel.html#resources
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Class_>
 */
class EloquentApiResources implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Class_) {
            return [];
        }

        if (! $node->extends instanceof Node\Name) {
            return [];
        }

        if ($node->extends->toString() !== JsonResource::class) {
            return [];
        }

        if (Str::endsWith($node->name->toString(), 'Resource')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Eloquent resources must be named with a `Resources` suffix, such as VideoResource.'
            )->build(),
        ];
    }
}
