<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @template T of Node
 * @implements \PHPStan\Rules\Rule<T>
 */
abstract class BaseNoDebugRule implements Rule
{
    protected string $message;

    protected array $haystack = [
        'dump',
        'dd',
    ];

    abstract public function getNodeType(): string;

    abstract public function processNode(Node $node, Scope $scope): array;

    protected function message(Scope $scope, string $namespace): ?string
    {
        if (! $this->hasCorrectNamespace($scope, $namespace)) {
            return null;
        }

        return match (strtolower($namespace)) {
            'app' => sprintf($this->message, 'app'),
            'test' => sprintf($this->message, 'test'),
            default => null,
        };
    }

    protected function hasDisallowedStatements(string $statement): bool
    {
        return in_array($statement, $this->haystack, true);
    }

    protected function hasCorrectNamespace(Scope $scope, string $namespace): bool
    {
        return str_starts_with($scope->getNamespace(), ucfirst($namespace));
    }
}
