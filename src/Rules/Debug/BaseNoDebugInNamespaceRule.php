<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name as NodeName;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/** @implements \PHPStan\Rules\Rule<MethodCall|StaticCall|FuncCall> */
abstract class BaseNoDebugInNamespaceRule implements Rule
{
    protected MethodCall|StaticCall|FuncCall|NodeName $name;

    protected Scope $scope;

    protected string $message;

    protected array $haystack = [
        'dump',
        'dd',
    ];

    abstract public function getNodeType(): string;

    abstract public function processNode(Node $node, Scope $scope): array;

    protected function message(Node|StaticCall|Identifier|MethodCall|FuncCall $node, string $namespace): ?string
    {
        if (! $this->hasDisallowedStatementsIn($node, $namespace)) {
            return null;
        }

        return match ($namespace) {
            'app' => sprintf($this->message, 'app'),
            'test' => sprintf($this->message, 'test'),
            default => null,
        };
    }

    private function hasDisallowedStatementsIn(Node|StaticCall|Identifier|MethodCall|FuncCall $node, string $namespace): bool
    {
        $startsWithNamespace = str_starts_with($this->scope->getNamespace(), ucfirst($namespace));

        return $startsWithNamespace && in_array($node->name->toString(), $this->haystack, true);
    }
}
