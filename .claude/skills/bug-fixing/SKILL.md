---
name: bug-fixing
description: "Test-driven bug fixing workflow. Activates when: fixing bugs, debugging issues, resolving defects, investigating errors, or when user mentions: bug, fix, broken, not working, error, issue, defect, regression."
argument-hint: [bug description]
---

# Test-Driven Bug Fixing

A disciplined approach to fixing bugs: **reproduce first, fix second**. Write a failing test that captures the bug, then fix the code to make it pass.

## Core Principle

**Never start by trying to fix the bug.** Instead:

1. Understand the bug
2. Write a test that reproduces it (fails)
3. Fix the bug
4. Verify the test passes
5. Run full quality checks

## Workflow

### Phase 1: Understand the Bug

1. **Gather information** — read the bug report, error messages, and relevant source files
2. **Identify the scope** — which rule(s) or component(s) are involved?
3. **Confirm understanding** — summarize the bug and get user confirmation

### Phase 2: Write the Failing Test

Write a test that:
1. Reproduces the exact scenario that triggers the bug
2. Fails with the current code (proving the bug exists)
3. Will pass when the bug is fixed

Tests in this project extend `PHPStan\Testing\RuleTestCase`:

```php
public function testRule(): void
{
    $this->analyse([__DIR__ . '/stubs/bug-scenario.php'], [
        ['Expected error message', 10],  // line number
    ]);
}
```

Create test stub files in `tests/Rules/stubs/` that trigger the buggy behavior.

### Phase 3: Verify Test Fails

```bash
vendor/bin/phpunit --filter=testMethodName
```

**If the test passes**: The bug may not be what we thought. Revisit Phase 1.
**If the test fails**: Proceed to fixing.

### Phase 4: Fix the Bug

Fix the rule implementation in `src/Rules/`. Do NOT modify the test.

### Phase 5: Verify the Fix

Run quality checks using the `backend-quality` skill:
1. `vendor/bin/pint --dirty`
2. `composer phpstan`
3. `composer test`

All checks must pass with 0 errors/failures.

## Test Writing Guidelines

### Test the Specific Scenario

```php
// Good - tests the specific bug scenario
public function testRuleDoesNotFlagValidRouteGroupWithClosure(): void

// Bad - too generic
public function testRule(): void
```

### Create Focused Stubs

Each stub file should isolate the specific scenario being tested. Don't overload existing stubs with unrelated test cases.
