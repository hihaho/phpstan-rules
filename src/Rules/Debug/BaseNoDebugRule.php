<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @template T of \PhpParser\Node
 * @implements \PHPStan\Rules\Rule<T>
 */
abstract class BaseNoDebugRule implements Rule
{
    /**
     * @var list<string>
     */
    protected array $debugStatements = [
        'dump',
        'dd',
        'ddd',
        'ray',
        'print_r',
        'var_dump',
    ];

    protected function isDisallowedStatement(string $statement): bool
    {
        return in_array($statement, $this->debugStatements, true);
    }

    protected function namespaceStartsWith(Scope $scope, string $namespace): bool
    {
        $scopeNamespace = $scope->getNamespace();

        if ($scopeNamespace === null) {
            return false;
        }

        if ($namespace === $scopeNamespace) {
            return true;
        }

        return str_starts_with($scopeNamespace, rtrim($namespace, '\\') . '\\');
    }
}
