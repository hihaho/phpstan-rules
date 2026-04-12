---
name: phpstan-developer
description: Build PHPStan rules, collectors, and extensions that analyze PHP code for custom errors. Use when asked to create, modify, or explain PHPStan rules, collectors, or type extensions. Triggers on requests like "write a PHPStan rule to...", "create a PHPStan rule that...", "add a PHPStan rule for...", "write a collector for...", or when working on a phpstan extension package.
---

# PHPStan Extension Builder

PHPStan finds bugs by traversing the PHP-Parser AST, resolving types via PHPStan's type system, and reporting errors from `processNode()`.

## Workflow

1. Identify the **PHP-Parser node type** to target — use `var_dump(get_class($node))` with `Node::class` as a temporary `getNodeType()` to discover node types, or check the php-parser docs
2. For **cross-file analysis** (e.g. "find unused things", "check all calls to X"), use a **Collector** to gather data and a `CollectedDataNode` rule to report — see references/collectors.md
3. Write the **Rule class** extending nothing — implement `Rule` interface directly
4. Write the **test class** extending `RuleTestCase` with fixture PHP files
5. Register the rule in a **neon config** file

## Rule Skeleton

```php
<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\IdentifierRuleError;

/**
 * @implements Rule<MethodCall>
 */
final class MyRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Return [] for no error, or build errors:
        return [
            RuleErrorBuilder::message('Something is wrong.')
                ->identifier('myRule.something')  // required: camelCase.dotSeparated
                ->build(),
        ];
    }
}
```

## `processNode()` Return Values

| Return | Effect |
|--------|--------|
| `[]` | No errors — node is fine |
| `[RuleErrorBuilder::...->build()]` | Report one or more errors |

Return type is always `list<IdentifierRuleError>`. Never return a single object — always wrap in an array.

## RuleErrorBuilder API

```php
RuleErrorBuilder::message('Error message text.')   // required
    ->identifier('category.specific')              // required; pattern: /[a-z][a-z0-9]*(\.[a-z0-9]+)*/
    ->line($node->getStartLine())                  // override line number
    ->tip('Suggestion to fix this.')               // optional tip shown to user
    ->addTip('Additional tip.')                    // add more tips
    ->discoveringSymbolsTip()                      // standard "class not found" tip
    ->nonIgnorable()                               // cannot be suppressed with @phpstan-ignore
    ->fixNode($node, fn (Node $n) => $modified)   // experimental: provide an automatic fix
    ->build()                                      // returns IdentifierRuleError
```

**Fixable errors** — `->fixNode()` attaches an AST transformation callable to the error. When the user runs `phpstan analyse --fix` (or their editor's PHPStan integration applies fixes), PHPStan replaces the original node with the result of the callable. The callable receives the original node and must return a replacement node of the same type. This is marked `@internal Experimental` in the source but is used throughout PHPStan core. See **references/testing.md** for how to test fixes.

> **When the fix is complex, use Rector instead.** `fixNode()` is limited to replacing a single node in-place. If the fix needs to add imports, restructure multiple nodes, move code, or make changes across more than one location in the file, write a Rector rule instead. Rector is purpose-built for multi-step AST transformations and handles pretty-printing, import resolution, and edge cases that `fixNode()` cannot. PHPStan finds the problem; Rector fixes it.

**For CollectedDataNode rules** (cross-file), you must set file and line explicitly:

```php
RuleErrorBuilder::message('...')
    ->file('/path/to/file.php')
    ->line(42)
    ->identifier('myRule.something')
    ->build()
```

## Common Scope Methods

```php
$scope->getType($node)                    // Type of any Expr node
$scope->isInClass()                       // Currently inside a class?
$scope->getClassReflection()              // ClassReflection|null
$scope->getFunction()                     // FunctionReflection|null
$scope->isInAnonymousFunction()           // Inside a closure?
$scope->hasVariableType('varName')        // TrinaryLogic: yes/maybe/no
$scope->getVariableType('varName')        // Type of $varName
$scope->filterByTruthyValue($expr)        // Narrowed scope when $expr is true
$scope->isDeclareStrictTypes()            // strict_types=1 active?
$scope->resolveName($nameNode)            // Resolve self/parent/static to FQCN
```

**TrinaryLogic** — the result of all `is*()` and `has*()` checks. Has three states:
- `->yes()` — definitely true; use when you want **zero false positives**
- `->no()` — definitely false; use as an **early-return guard** to skip inapplicable nodes
- `->maybe()` — uncertain (mixed/union); use for **softer warnings** or combined checks

See **references/trinary-logic.md** for the full decision guide, logical operations, and patterns.

## Common Type Methods

Never use `instanceof` on PHPStan types — always use the `is*()` methods:

```php
$type = $scope->getType($node);

$type->isString()->yes()         // Is definitely a string?
$type->isObject()->yes()         // Is definitely an object?
$type->isNull()->yes()           // Is always null?
$type->isArray()->yes()          // Is always an array?
$type->getObjectClassNames()     // list<string> of class names
$type->getConstantStrings()      // list<ConstantStringType>
$type->describe(VerbosityLevel::typeOnly())  // Human-readable type description
```

## Writing Tests

Every rule needs a test class and at least one fixture file. Use one fixture file per scenario.

**Test class** (`tests/Rules/MyRuleTest.php`):

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
        return new MyRule();
    }

    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/data/my-rule.php'],
            [
                ['Error message text.', 10],       // [message, line]
                ['Another error.', 25, 'A tip.'],  // [message, line, tip] (optional)
            ]
        );
    }

    public function testNoErrors(): void
    {
        $this->analyse([__DIR__ . '/data/my-rule-clean.php'], []);
    }
}
```

**Fixture file** (`tests/Rules/data/my-rule.php`) — plain PHP file with code that triggers the rule:

```php
<?php

declare(strict_types=1);

namespace App\Tests\PHPStan\Rules\Data;

// This call should trigger the rule on line 10:
$obj->forbiddenMethod();
```

**Key rules:**
- One scenario per fixture file — do not mix multiple unrelated scenarios in one file
- Fixture files live in a `data/` subdirectory relative to the test class
- The `analyse()` assertion fails if any unexpected errors appear, or expected errors are missing
- If a rule has constructor dependencies, create them manually in `getRule()`

See **references/testing.md** for: additional config files, injecting services, TypeInferenceTestCase.

## Registration (phpstan.neon / extension.neon)

**Shorthand (simple rules with no constructor dependencies):**

```neon
rules:
    - App\PHPStan\Rules\MyRule
```

**Full service registration (for rules with dependencies):**

```neon
services:
    -
        class: App\PHPStan\Rules\MyRule
        tags:
            - phpstan.rules.rule

    -
        class: App\PHPStan\Collectors\MyCollector
        tags:
            - phpstan.collector
```

## Reference Files

- **references/trinary-logic.md** — TrinaryLogic in depth: when to use yes/no/maybe, and/or/negate, patterns
- **references/collectors.md** — Collector interface, cross-file analysis, CollectedDataNode pattern
- **references/testing.md** — Full test structure, injecting services, additional config files, TypeInferenceTestCase
- **references/scope-api.md** — Full Scope API, ReflectionProvider, ClassReflection methods
- **references/virtual-nodes.md** — PHPStan virtual nodes (InClassNode, InClassMethodNode, FileNode, etc.)
- **references/extensions.md** — Dynamic return type extensions, type specifying extensions, reflection extensions, neon service tags
