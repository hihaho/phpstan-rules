<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\NamingClasses;

use Hihaho\PhpstanRules\Traits\HasUrlTip;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @see https://guidelines.hihaho.com/laravel.html#resources
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Class_>
 */
class EloquentApiResources implements Rule
{
    use HasUrlTip;

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
        //
    }

    public function docs(): string
    {
        return 'https://guidelines.hihaho.com/laravel.html#resources';
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->extends instanceof Node\Name) {
            return [];
        }

        $nameSpacedName = $node->namespacedName?->toString();

        if ($nameSpacedName === null) {
            return [];
        }

        if (! Str::startsWith($nameSpacedName, 'App\Http\Resources')) {
            return [];
        }

        if (! $this->reflectionProvider->hasClass($nameSpacedName)) {
            return [];
        }

        $classReflection = $this->reflectionProvider->getClass($nameSpacedName);

        return match (true) {
            $classReflection->isSubclassOf(ResourceCollection::class) => $this->processResourceCollection($node, $nameSpacedName),
            $classReflection->isSubclassOf(JsonResource::class) => $this->processResource($node, $nameSpacedName),
            default => [],
        };
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function processResourceCollection(Class_ $node, string $nameSpacedName): array
    {
        if (Str::endsWith($nameSpacedName, 'ResourceCollection')) {
            return [];
        }

        $name = str($node->name instanceof Node\Identifier ? $node->name->toString() : $nameSpacedName)
            ->replace(['Collection', 'Resource'], '')
            ->append('ResourceCollection');

        return [
            RuleErrorBuilder::message(
                "Eloquent resource collection {$nameSpacedName} must be named with a `ResourceCollection` suffix, such as {$name}."
            )
                ->tip($this->tip())
                ->identifier('hihaho.naming.classes.eloquentApiResourceCollections')
                ->build(),
        ];
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function processResource(Class_ $node, string $nameSpacedName): array
    {
        if (Str::endsWith($nameSpacedName, 'Resource')) {
            return [];
        }

        $name = str($node->name instanceof Node\Identifier ? $node->name->toString() : $nameSpacedName)
            ->append('Resource');

        return [
            RuleErrorBuilder::message(
                "Eloquent resource {$nameSpacedName} must be named with a `Resource` suffix, such as {$name}."
            )
                ->tip($this->tip())
                ->identifier('hihaho.naming.classes.eloquentApiResources')
                ->build(),
        ];
    }
}
