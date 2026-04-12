# PHPStan Rules Package

This is `hihaho/phpstan-rules`, a PHPStan extension package that enforces hihaho's Laravel coding conventions through static analysis. This is **not** a Laravel application — it is a standalone Composer package.

## Project Structure

```
src/
├── Rules/             # PHPStan rules (one class per rule)
│   └── Debug/         # Debug statement detection rules
├── Traits/            # Shared traits (ChecksNamespace)
tests/
├── Rules/             # Tests for each rule (PHPStan RuleTestCase)
│   └── stubs/         # Test fixture PHP/Blade files
extension.neon         # PHPStan extension registration
phpstan.neon.dist      # PHPStan analysis configuration
pint.json              # Laravel Pint code style config
```

Class-naming and routing conventions are enforced by the sister package
[hihaho/rector-rules](https://github.com/hihaho/rector-rules), which
auto-fixes them rather than just reporting.

## Dependencies

- **PHP**: ^8.3
- **PHPStan**: ^2.1
- **Laravel Support** (illuminate/support): ^11.31|^12.0|^13.0
- **Testing**: PHPUnit ^11.5
- **Code Style**: Laravel Pint ^1.21

## Development Commands

```bash
composer test                # Run PHPUnit tests
composer fix-cs              # Run Laravel Pint formatter (alias: composer format)
composer phpstan             # Run PHPStan analysis
composer phpstan-clear-cache # Clear PHPStan cache
composer rector              # Run Rector transformations
composer qa                  # Run format, rector, phpstan, test
```

## Repository

- **Owner**: `hihaho`
- **Repository**: `phpstan-rules`
- **Default branch**: `main`

---

## PHP Conventions

- Always use `declare(strict_types=1);`
- Use `private` visibility by default for methods, properties, and constants
- Always use curly braces for control structures, even for single-line bodies
- Use PHP 8 constructor property promotion
- Always use explicit return type declarations
- Use appropriate PHP type hints for all method parameters
- Add a space after the unary not operator: `if (! $foo)`
- Omit docblocks when methods are fully type-hinted
- Prefer string interpolation over `sprintf` and concatenation
- Never use `empty()` — use explicit checks instead
- Prefer PHPDoc blocks over inline comments

---

## Testing

- Tests use PHPStan's `RuleTestCase` base class
- Each rule has a corresponding test in `tests/Rules/`
- Test stubs (fixture PHP/Blade files) live alongside the tests in `stubs/` subdirectories
- Run all tests: `composer test`
- Run specific test: `vendor/bin/phpunit --filter=TestClassName`
- Every change must have test coverage — write or update tests, then run them

## Creating a New Rule

1. Create the rule class in `src/Rules/` implementing PHPStan's `Rule<T>` interface
2. Register it in `extension.neon` — under `rules:` if the rule has no constructor dependencies, or under `services:` with the `phpstan.rules.rule` tag when it needs DI (e.g. `ReflectionProvider`)
3. Create test stub files in `tests/Rules/stubs/` (or a sibling `stubs/` directory next to the test)
4. Create a test extending `PHPStan\Testing\RuleTestCase` in `tests/Rules/`
5. Use the `ChecksNamespace` trait if the rule needs to filter by namespace prefix
6. Run `composer fix-cs` and `composer phpstan` before finalizing

## Quality Standards

- PHPStan level: `max`
- Type coverage: 100% (return, param, property, declare)
- Cognitive complexity: class max 12, function max 10
- Code style: Laravel Pint with `laravel` preset

---

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

---

## Skills

Available skills for this project:

- `backend-quality` — Runs code quality checks: Pint, PHPStan, and tests. Activate after making changes to PHP files.
- `bug-fixing` — Test-driven bug fixing workflow. Activates when fixing bugs or investigating errors.
- `code-review` — Reviews recent code changes for improvements. Activates when reviewing code.
- `evaluate` — Evaluate implementation and fix any issues found. Activates when self-reviewing code.
- `pull-requests` — Creates and manages pull requests. Activates when creating or working on PRs.
- `pr-review-feedback` — Applies PR review feedback with critical evaluation. Activates when addressing PR feedback.
