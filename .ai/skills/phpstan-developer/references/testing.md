# PHPStan Rule Testing Reference

## RuleTestCase Overview

All rule tests extend `PHPStan\Testing\RuleTestCase`. The test framework:

- Runs the full PHPStan analyser on provided fixture files
- Compares reported errors against your expected list
- Fails if any expected error is missing OR any unexpected error appears

## Minimal Test Class

```php
<?php

declare(strict_types=1);

namespace App\Tests\PHPStan\Rules;

use App\PHPStan\Rules\MyRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<MyRule>
 */
final class MyRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        // Instantiate directly; inject dependencies manually
        return new MyRule(
            new SomeDependency(),
        );
    }

    public function testDetectsError(): void
    {
        $this->analyse(
            [__DIR__ . '/data/my-rule-error.php'],  // fixture files to analyse
            [
                ['Error message text.', 10],         // [message, line]
                ['Another error.', 25, 'A tip.'],    // [message, line, tip] — tip is optional
            ]
        );
    }

    public function testCleanCode(): void
    {
        $this->analyse([__DIR__ . '/data/my-rule-clean.php'], []);  // expect no errors
    }
}
```

## analyse() Method

```php
$this->analyse(
    array $files,           // list of absolute file paths to analyse
    array $expectedErrors   // list of [string $message, int $line, ?string $tip]
): void
```

Errors are matched by message text, line number, and optionally tip. The comparison is exact — whitespace and casing matter.

## Fixture Files

Fixture files are ordinary PHP files in a `data/` subdirectory. Write code that should trigger (or not trigger) the rule:

```
tests/
└── Rules/
    ├── MyRuleTest.php
    └── data/
        ├── my-rule-error.php     ← code that should trigger errors
        ├── my-rule-clean.php     ← code that should produce no errors
        └── my-rule-edge-case.php ← one fixture per distinct scenario
```

**One fixture per scenario** — keep fixture files focused. A fixture for "no errors" and a fixture for "reports error on method call" are separate files.

### Example fixture file

```php
<?php

// tests/Rules/data/my-rule-error.php

declare(strict_types=1);

namespace App\Tests\PHPStan\Rules\Data;

class Example
{
    public function run(): void
    {
        $this->forbiddenMethod(); // line 13 — error expected here
    }

    private function forbiddenMethod(): void {}
}
```

## Injecting Constructor Dependencies

Create dependencies inline in `getRule()`. For services that PHPStan provides, use the static factory methods on `RuleTestCase`:

```php
protected function getRule(): Rule
{
    // Access PHPStan's reflection provider
    $reflectionProvider = $this->createReflectionProvider();

    // Access PHPStan's broker (type system)
    $broker = $this->createBroker();

    return new MyRule($reflectionProvider);
}
```

## Testing with Collectors

Override `getCollectors()` to provide collectors alongside the rule:

```php
protected function getCollectors(): array
{
    return [
        new MyCollector(),
        new AnotherCollector(),
    ];
}
```

Both `getRule()` and `getCollectors()` must be present. The `analyse()` call then runs collectors and the reporting rule together.

## Additional Config Files

When the rule depends on services registered in a neon config (e.g. stubs, custom extensions), override `getAdditionalConfigFiles()`:

```php
public static function getAdditionalConfigFiles(): array
{
    return [
        __DIR__ . '/../../extension.neon',   // your extension config
    ];
}
```

This merges the config into the test analyser. Useful when:
- Your rule depends on a DynamicReturnTypeExtension registered in your extension
- You need stub files loaded
- You have parameters configured in neon

## TypeInferenceTestCase

For testing **type extensions** (not rules), use `PHPStan\Testing\TypeInferenceTestCase` instead:

```php
final class MyTypeExtensionTest extends TypeInferenceTestCase
{
    public function dataFileAsserts(): iterable
    {
        yield from $this->gatherAssertTypes(__DIR__ . '/data/type-assertions.php');
    }

    /** @dataProvider dataFileAsserts */
    public function testFileAsserts(string $assertType, string $file, mixed ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }
}
```

In the fixture file, use `assertType()` from PHPStan's testing helpers:

```php
<?php

// tests/Type/data/type-assertions.php

use function PHPStan\Testing\assertType;

$result = myFunction('hello');
assertType('string', $result);   // verify the inferred type
```

## Testing Fixable Rules

When a rule uses `->fixNode()`, you can assert that applying all fixes produces a specific expected file using the `fix()` method on `RuleTestCase`.

### The fix() method

```php
$this->fix(string $inputFile, string $expectedFile): void
```

It runs the rule against `$inputFile`, applies every fix provided by every `FixableNodeRuleError`, and asserts the resulting source matches `$expectedFile` exactly.

### File naming convention

Pair each fixture with a `.fixed` counterpart in the same `data/` directory:

```
tests/Rules/data/
├── my-rule.php          ← input: code that triggers the error
└── my-rule.php.fixed    ← expected output after all fixes are applied
```

### Example: backtick → shell_exec

Rule:

```php
return [
    RuleErrorBuilder::message('Backtick operator is deprecated. Use shell_exec() instead.')
        ->identifier('backtick.deprecated')
        ->fixNode($node, static fn () => new Node\Expr\FuncCall(
            new Node\Name('shell_exec'),
            [new Node\Arg($argExpr)],
        ))
        ->build(),
];
```

`data/my-rule.php`:

```php
<?php

$result = `ls -la`;
```

`data/my-rule.php.fixed`:

```php
<?php

$result = shell_exec('ls -la');
```

Test class:

```php
final class MyRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new MyRule();
    }

    public function testErrors(): void
    {
        $this->analyse(
            [__DIR__ . '/data/my-rule.php'],
            [['Backtick operator is deprecated. Use shell_exec() instead.', 3]],
        );
    }

    public function testFix(): void
    {
        $this->fix(
            __DIR__ . '/data/my-rule.php',
            __DIR__ . '/data/my-rule.php.fixed',
        );
    }

    public function testNoFix(): void
    {
        // When there are no fixable errors, pass the same file as both arguments.
        // The assertion passes because no changes are applied.
        $this->fix(
            __DIR__ . '/data/my-rule-clean.php',
            __DIR__ . '/data/my-rule-clean.php',
        );
    }
}
```

### What fixNode() does — and does not — do

- `fixNode($node, $cb)` passes the **original node** from the current analysis to `$cb`. The callback must return a node of the **same type** to replace it.
- PHPStan re-prints the modified AST back to source using its internal pretty-printer. Comments and whitespace outside the modified node are preserved; formatting inside the replaced node follows the printer's rules.
- Fixes are applied by `phpstan analyse --fix` on the command line, or surfaced to editors/IDEs via the language server.
- `fixNode` is marked `@internal Experimental` in PHPStan source — the API is stable enough for use but may change in future versions.

### When fixNode() is not enough — use Rector instead

`fixNode()` is intentionally narrow: it replaces **one node** with another node of the same type. If your fix requires anything beyond that, it will not fit cleanly into `fixNode()`:

| Situation | Use |
|-----------|-----|
| Replace a single expression or statement in-place | `fixNode()` |
| Add a `use` import at the top of the file | Rector |
| Restructure or reorder multiple nodes | Rector |
| Move code between methods or classes | Rector |
| Make coordinated changes across more than one location | Rector |
| Complex transformations with many edge cases | Rector |

In these cases, write the PHPStan rule to **detect** the problem and write a **Rector rule** to **fix** it. Use `->tip()` on the error to point users toward the Rector rule:

```php
return [
    RuleErrorBuilder::message('Service class must not depend on the container directly.')
        ->identifier('architecture.containerDependency')
        ->tip('Run the Rector rule App\\Rector\\InjectDependenciesDirectlyRector to fix this automatically.')
        ->build(),
];
```

This is the recommended separation of concerns: PHPStan finds the problem; Rector fixes it.

### Mutation pattern: modify and return the same node

You can mutate a clone of the node and return it, rather than building a new node from scratch:

```php
->fixNode($node->getOriginalNode(), static function (Node\Stmt\ClassMethod $method) {
    // Add the #[\Override] attribute
    $method->attrGroups[] = new Node\AttributeGroup([
        new Node\Attribute(new Node\Name\FullyQualified('Override')),
    ]);
    return $method;
})
```

Or remove something from a node:

```php
->fixNode($node->getOriginalNode(), function (Node\Stmt\ClassMethod $method) {
    // Remove the #[\Override] attribute
    $method->attrGroups = $this->filterOverrideAttribute($method->attrGroups);
    return $method;
})
```

---

## Common Pitfalls

- **Wrong line number**: PHPStan counts from 1. If the error is on the first line, use `1`.
- **Tip mismatch**: The tip text must match exactly, including punctuation.
- **Extra errors**: If other rules also fire on your fixture, the test fails. Use `// @phpstan-ignore` to suppress unrelated errors in fixture files, or use a narrowly-scoped fixture.
- **Reflection not found**: If your rule uses `ReflectionProvider` and the class isn't autoloaded, PHPStan won't find it. Ensure the fixture file's namespace resolves via Composer autoload, or add a stub.
