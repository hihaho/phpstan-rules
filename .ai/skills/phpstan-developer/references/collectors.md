# PHPStan Collectors Reference

Collectors enable rules that reason across **multiple files** — for example, finding unused classes, tracking all call sites of a method, or detecting missing implementations. PHPStan runs analysis across parallel processes; collectors gather data per file which is then merged and made available to rules via `CollectedDataNode`.

## When to Use a Collector

Use a collector when your rule needs to see code **from more than one file** before reporting an error. Examples:

- "Report any trait declared but never used" — needs to know all traits AND all usages
- "Report any method that is always called with a literal string" — needs all call sites
- "Find classes that implement an interface but miss a specific annotation"

For single-file checks, a regular rule is sufficient — no collector needed.

## Collector Interface

```php
<?php

declare(strict_types=1);

namespace App\PHPStan\Collectors;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<ClassMethod, array{class: string, method: string, line: int}>
 */
final class MyCollector implements Collector
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return array{class: string, method: string, line: int}|null
     */
    public function processNode(Node $node, Scope $scope)
    {
        if (!$scope->isInClass()) {
            return null;  // return null to skip this node
        }

        $classReflection = $scope->getClassReflection();

        return [
            'class'  => $classReflection->getName(),
            'method' => $node->name->toString(),
            'line'   => $node->getStartLine(),
        ];
    }
}
```

**Key rules:**
- Return `null` to skip collecting for this node
- Return any **scalar or array of scalars** when collecting — data is serialised between processes, so objects are not allowed
- The generic `@implements Collector<NodeType, DataType>` documents what is collected

## Rule that Consumes Collected Data

A rule that uses collected data targets `CollectedDataNode::class`. It runs **once** after all files are analysed, with all collected data merged.

```php
<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\IdentifierRuleError;
use App\PHPStan\Collectors\MyCollector;

/**
 * @implements Rule<CollectedDataNode>
 */
final class MyCollectorRule implements Rule
{
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @param CollectedDataNode $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Returns array<filePath, list<CollectedValue>>
        $collectedData = $node->get(MyCollector::class);

        $errors = [];

        foreach ($collectedData as $filePath => $items) {
            foreach ($items as $item) {
                // $item is array{class: string, method: string, line: int}
                if ($this->shouldReport($item)) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf('Problem in %s::%s()', $item['class'], $item['method'])
                    )
                        ->file($filePath)       // REQUIRED for CollectedDataNode rules
                        ->line($item['line'])   // REQUIRED for CollectedDataNode rules
                        ->identifier('myRule.problem')
                        ->build();
                }
            }
        }

        return $errors;
    }

    private function shouldReport(array $item): bool
    {
        return true; // your logic here
    }
}
```

**Important:** CollectedDataNode rules have no file/line context of their own. Always set `.file()` and `.line()` on every error using data from the collected values.

## Partial Analysis Check

```php
public function processNode(Node $node, Scope $scope): array
{
    // Collectors only have complete data during full project analysis.
    // When only specific files are passed, skip cross-file checks.
    if ($node->isOnlyFilesAnalysis()) {
        return [];
    }

    // ...
}
```

## Pattern: Two Collectors, One Rule

A common pattern is two collectors (declarations + usages) combined in one reporting rule:

```php
/**
 * @implements Collector<Node\Stmt\Trait_, array{name: string, line: int}>
 */
final class TraitDeclarationCollector implements Collector { /* ... */ }

/**
 * @implements Collector<Node\Stmt\TraitUse, list<string>>
 */
final class TraitUsageCollector implements Collector { /* ... */ }

/**
 * @implements Rule<CollectedDataNode>
 */
final class UnusedTraitRule implements Rule
{
    public function getNodeType(): string { return CollectedDataNode::class; }

    public function processNode(Node $node, Scope $scope): array
    {
        $declarations = $node->get(TraitDeclarationCollector::class);
        $usages = $node->get(TraitUsageCollector::class);

        // Flatten all used trait names
        $usedTraits = [];
        foreach ($usages as $fileUsages) {
            foreach ($fileUsages as $traitList) {
                foreach ($traitList as $traitName) {
                    $usedTraits[$traitName] = true;
                }
            }
        }

        $errors = [];
        foreach ($declarations as $filePath => $fileDeclarations) {
            foreach ($fileDeclarations as ['name' => $name, 'line' => $line]) {
                if (!isset($usedTraits[$name])) {
                    $errors[] = RuleErrorBuilder::message("Trait {$name} is never used.")
                        ->file($filePath)
                        ->line($line)
                        ->identifier('trait.unused')
                        ->build();
                }
            }
        }

        return $errors;
    }
}
```

## Registration

```neon
services:
    -
        class: App\PHPStan\Collectors\TraitDeclarationCollector
        tags:
            - phpstan.collector

    -
        class: App\PHPStan\Collectors\TraitUsageCollector
        tags:
            - phpstan.collector

    -
        class: App\PHPStan\Rules\UnusedTraitRule
        tags:
            - phpstan.rules.rule
```

## Testing Rules with Collectors

In the test class, override `getCollectors()` alongside `getRule()`:

```php
final class UnusedTraitRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new UnusedTraitRule();
    }

    protected function getCollectors(): array
    {
        return [
            new TraitDeclarationCollector(),
            new TraitUsageCollector(),
        ];
    }

    public function testUnusedTrait(): void
    {
        $this->analyse(
            [__DIR__ . '/data/unused-trait.php'],
            [
                ['Trait MyTrait is never used.', 5],
            ]
        );
    }
}
```
