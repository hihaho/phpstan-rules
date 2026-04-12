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
```

## Conventions

- `declare(strict_types=1)` in all PHP files
- `private` visibility by default instead of `protected`
- Curly braces for all control structures
- Space after unary not: `if (! $foo)`
- Omit docblocks when fully type-hinted
- 100% type coverage, PHPStan level max
