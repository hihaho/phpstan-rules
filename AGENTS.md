# PHPStan Rules Package — Agent Context

This is `hihaho/phpstan-rules`, a PHPStan extension package. **Not** a Laravel application.

## Key Facts

- Rules live in `src/Rules/`, tests in `tests/Rules/`, stubs in `tests/Rules/stubs/`
- Rules implement PHPStan's `Rule<T>` interface and are registered in `extension.neon`
- Tests extend `PHPStan\Testing\RuleTestCase`
- PHP ^8.3, PHPStan ^2.1, PHPUnit ^11.5

## Commands

```bash
composer test                # Run tests
composer fix-cs              # Run Pint formatter
composer phpstan             # Run PHPStan analysis
composer rector              # Run Rector transformations
composer qa                  # Run format, rector, phpstan, test
```

## Conventions

- `declare(strict_types=1)` in all PHP files
- `private` visibility by default instead of `protected`
- Curly braces for all control structures
- Space after unary not: `if (! $foo)`
- Omit docblocks when fully type-hinted
- 100% type coverage, PHPStan level max

<package-boost-guidelines>
## Verification Before Completion

Before claiming any work is complete or successful, run the verification command fresh and confirm the output. Evidence before claims, always.

### Required Before Any Completion Claim

1. **Run** the relevant command (in the current message, not from memory)
2. **Read** the full output
3. **Confirm** it supports the claim
4. **Then** state the result with evidence

### During Development (after each change)

| Claim            | Required verification                              |
|------------------|----------------------------------------------------|
| Code style clean | `vendor/bin/pint --dirty --format agent` output    |
| Tests pass       | Related tests pass via `--filter` or specific file |
| Bug fixed        | Previously failing test now passes                 |

### At Completion Only (feature/phase done, before PR)

These are slow checks — only run them once at the very end:

| Claim             | Required verification                                           |
|-------------------|-----------------------------------------------------------------|
| Rector ran clean  | `vendor/bin/rector process` showing 0 changes                   |
| PHPStan clean     | `vendor/bin/phpstan analyse --memory-limit=2G` showing 0 errors |
| Full suite passes | `vendor/bin/phpunit` output showing 0 failures                  |
| Feature complete  | All above checks pass                                           |

### Always Capture Command Output

Append `|| true` to all verification commands (tests, linting, type checks) so the output is always captured, even on failure. Without it, a non-zero exit code can hide the output, forcing an expensive second run just to read the errors.

```bash
# CORRECT — output always visible
vendor/bin/phpunit --filter=testName || true
vendor/bin/pint --dirty --format agent || true

# WRONG — output lost on failure, wastes time re-running
vendor/bin/phpunit --filter=testName
```

### Never Use Without Evidence

- "should work now"
- "that should fix it"
- "looks correct"
- "I'm confident this works"

These phrases indicate missing verification. Run the command first, then report what actually happened.
</package-boost-guidelines>
