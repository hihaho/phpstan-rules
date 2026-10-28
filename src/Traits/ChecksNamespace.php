<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use PHPStan\Analyser\Scope;

trait ChecksNamespace
{
    protected function namespaceStartsWith(Scope $scope, string $namespace): bool
    {
        return $this->scopeNamespaceMatchesPrefix($scope->getNamespace(), $namespace);
    }

    /**
     * @param  list<string>  $namespaces
     */
    protected function namespaceStartsWithAny(Scope $scope, array $namespaces): bool
    {
        $scopeNamespace = $scope->getNamespace();

        return array_any($namespaces, fn (string $namespace) => $this->scopeNamespaceMatchesPrefix($scopeNamespace, $namespace));
    }

    private function scopeNamespaceMatchesPrefix(?string $scopeNamespace, string $namespace): bool
    {
        if ($scopeNamespace === null) {
            return false;
        }

        if ($namespace === $scopeNamespace) {
            return true;
        }

        return str_starts_with($scopeNamespace, rtrim($namespace, '\\') . '\\');
    }
}
