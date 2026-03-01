# PHPStan Rules Package

This is `hihaho/phpstan-rules`, a PHPStan extension package that enforces hihaho's Laravel coding conventions through static analysis. This is **not** a Laravel application — it is a standalone Composer package.

## Project Structure

```
src/
├── Rules/             # PHPStan rules (one class per rule)
│   ├── Debug/         # Debug statement detection rules
│   ├── NamingClasses/ # Class naming convention rules
│   └── Routing/       # Route configuration rules
├── Traits/            # Shared traits (HasUrlTip)
tests/
├── Rules/             # Tests for each rule (PHPStan RuleTestCase)
│   └── stubs/         # Test fixture PHP/Blade files
extension.neon         # PHPStan extension registration
phpstan.neon.dist      # PHPStan analysis configuration
pint.json              # Laravel Pint code style config
```

## Dependencies

- **PHP**: ^8.2
- **PHPStan**: ^2.1
- **Laravel Support** (illuminate/support): ^11.31|^12.0|^13.0
- **Testing**: PHPUnit ^11.5
- **Code Style**: Laravel Pint ^1.21

## Development Commands

```bash
composer test                # Run PHPUnit tests
composer fix-cs              # Run Laravel Pint formatter
composer phpstan             # Run PHPStan analysis
composer phpstan-clear-cache # Clear PHPStan cache
```

## Repository

- **Owner**: `hihaho`
- **Repository**: `phpstan-rules`
- **Default branch**: `main`

---

## PHP Conventions

- Always use `declare(strict_types=1);`
- Mark classes as `final` by default
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
2. Register it as a service in `extension.neon` with the `phpstan.rules.rule` tag
3. Create test stub files in `tests/Rules/stubs/`
4. Create a test extending `PHPStan\Testing\RuleTestCase` in `tests/Rules/`
5. Use `HasUrlTip` trait if the rule should link to guidelines documentation
6. Run `composer fix-cs` and `composer phpstan` before finalizing

## Quality Standards

- PHPStan level: `max`
- Type coverage: 100% (return, param, property, declare)
- Cognitive complexity: class max 12, function max 10
- Code style: Laravel Pint with `laravel` preset

---

## Verification Before Completion

Before claiming work is complete, run and confirm output:

| Claim              | Required verification                               |
|--------------------|------------------------------------------------------|
| Tests pass         | `composer test` output showing 0 failures            |
| Code style clean   | `vendor/bin/pint --dirty` output (no changes needed) |
| Static analysis    | `composer phpstan` showing 0 errors                  |

---

## Skills

Available skills for this project:

- `backend-quality` — Runs code quality checks: Pint, PHPStan, and tests. Activate after making changes to PHP files.
- `bug-fixing` — Test-driven bug fixing workflow. Activates when fixing bugs or investigating errors.
- `code-review` — Reviews recent code changes for improvements. Activates when reviewing code.
- `evaluate` — Evaluate implementation and fix any issues found. Activates when self-reviewing code.
- `pull-requests` — Creates and manages pull requests. Activates when creating or working on PRs.
- `pr-review-feedback` — Applies PR review feedback with critical evaluation. Activates when addressing PR feedback.
