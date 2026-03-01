<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use PHPStan\Analyser\Scope;

trait ChecksNamespace
{
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
