---
name: backend-quality
description: "Runs code quality checks: Pint, PHPStan, and tests. Activate after making changes to PHP files, or when user mentions: phpstan, pint, code quality, static analysis, code style, run checks."
---

# Code Quality Checks

Run all quality checks after making changes to PHP files. All checks must pass before work is considered complete.

## When to Use This Skill

- PHP files have been created or modified
- Finalizing a feature, bug fix, or refactor
- The user asks to run checks, PHPStan, Pint, or tests
- Before creating a PR

## Checks (Run in Order)

### 1. Pint (Code Style)

```bash
vendor/bin/pint --dirty
```

Fix any formatting issues. Re-run until clean.

### 2. PHPStan (Static Analysis)

```bash
composer phpstan
```

Must show 0 errors. Fix any issues found and re-run Pint after fixes.

### 3. Tests

```bash
# All tests
composer test

# Specific test file
vendor/bin/phpunit --filter=TestClassName

# Specific test method
vendor/bin/phpunit --filter=testMethodName
```

All related tests must pass.

## Quick Reference

| Check           | Command                  | Pass criteria  |
|-----------------|--------------------------|----------------|
| Code style      | `vendor/bin/pint --dirty`| No changes     |
| Static analysis | `composer phpstan`       | 0 errors       |
| Tests           | `composer test`          | 0 failures     |

## Important

- Run Pint **before** PHPStan — style fixes can resolve some PHPStan issues.
- Run Pint **again after** PHPStan fixes — PHPStan fixes may introduce style issues.
- Never skip a check. All three must pass.
