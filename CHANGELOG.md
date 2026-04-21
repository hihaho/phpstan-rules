# Changelog

All notable changes to `hihaho/phpstan-rules` will be documented in this file.

## v3.1.0 - 2026-04-21

### Added

- Three rules preventing unvalidated reads from `Illuminate\Http\Request` in application code:
  - **`NoUnsafeRequestDataRule`** — flags `MethodCall` on a `Request` or `FormRequest` receiver whose method is in `noUnsafeRequestData.unsafeMethods`. Defaults: `input`, `all`, `get`, `query`, `post`, `only`, `except`, `collect`, `string`, `str`, `integer`, `boolean`, `float`, `json`, `keys`, `fluent`, `array`, `date`, `enum`, `enums`, `file`, `allFiles`. Union-typed receivers (`Request|Other`) are flagged when any member is-a `Request`. Scope-class exemption walks the inheritance chain — custom base `FormRequest` classes are transparent. Identifier: `hihaho.validation.noUnsafeRequestData`.
  - **`NoUnsafeRequestHelperRule`** — flags the `request('key')` direct-argument helper form. Uses PHPStan's `ReflectionProvider` to resolve imports and aliases (`use function request as foo`). Error message interpolates the literal key for grep-friendly triage. Zero-argument `request()` is not flagged — chained method calls on its return are caught by `NoUnsafeRequestDataRule`. Identifier: `hihaho.validation.noUnsafeRequestHelper`.
  - **`NoUnsafeRequestFacadeRule`** — flags static calls on `Illuminate\Support\Facades\Request` (e.g. `Request::boolean('debug')`, `Request::file('attachment')`). Identifier: `hihaho.validation.noUnsafeRequestFacade`.
- `noUnsafeRequestData` configuration block with `unsafeMethods`, `namespaces`, and `excludeNamespaces`. `excludeNamespaces` defaults to `App\Providers` and `App\Http\Responses` — both areas receive raw `Request` via framework-dictated signatures (`RateLimiter::for(...)` closures, Fortify response contracts) with no FormRequest entry point. `App\Http\Resources` is intentionally **not** defaulted; add it in your own config if `JsonResource::toArray(Request)` reading raw request data is acceptable for your project.
- `ChecksNamespace::namespaceStartsWithAny()` helper for list-based namespace matching.

### Changed

- Raw readers on a `FormRequest` typehint in a controller are now flagged. `FormRequest` auto-validation runs on dispatch, but inherited readers still return the full unvalidated payload including keys outside `rules()`. Use `$request->validated()`, `$request->safe()`, or the array returned by `$request->validate([...])` instead. For Stringable / int / bool chaining, `$request->safe()->string('key')` mirrors `$request->string('key')` against validated input.

See [README.md](README.md#nounsaferequestdatarule) for full rule descriptions, configuration keys, and baseline categories.

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v3.0.0...v3.1.0

## v3.0.0 - 2026-04-12

Major version. Class-naming and routing conventions move to the sibling package [`hihaho/rector-rules`](https://github.com/hihaho/rector-rules) as auto-fixers. This package keeps the rules that have no auto-fix counterpart. See [UPGRADING.md](UPGRADING.md) for migration steps.

### Removed

- `Rules\NamingClasses\Commands`, `Mail`, `Notifications`, `EloquentApiResources` (and `SuffixableRule` base). Replaced by `AddCommandSuffixRector`, `AddMailSuffixRector`, `AddNotificationSuffixRector`, and `AddResourceSuffixRector` in [`hihaho/rector-rules`](https://github.com/hihaho/rector-rules).
- `Rules\Routing\SlashInUrl` and `Rules\Routing\RouteGroups`. Replaced by `NormalizeRoutePathRector` and `RouteGroupArrayToMethodsRector` in [`hihaho/rector-rules`](https://github.com/hihaho/rector-rules).
- PHP 8.2 support. Minimum is now `^8.3`.
- `illuminate/{console,http,mail,notifications,routing}` dev deps. Only `illuminate/support` remains in `require`.

### Changed

- `ChainedNoDebugInNamespaceRule` now narrows matches to methods declared by a class in the `Illuminate\` namespace. A domain class with its own `->dump()` method is no longer a false positive.
- `StaticChainedNoDebugInNamespaceRule` narrows the same way, with a `Facade` subclass fallback so `Cache::dump()` and other facades without `@method static ... dump()` annotations still flag via the `Facade::__callStatic` proxy.
- `NoInvadeInAppCode` identifier category corrected from `hihaho.debug.*` to `hihaho.generic.*` (not a debug rule).
- All rules now use `final readonly class`, `#[\Override]` on interface implementations, and explicit `@return list<IdentifierRuleError>` annotations.
- `OnlyAllowFacadeAliasInBlade` keeps `\ReflectionClass` runtime reflection deliberately. PHPStan's `ReflectionProvider` does not invoke SPL autoloaders, so static discovery would silently miss every lazy Laravel facade alias. Documented in-source.
- `extension.neon`: rules shorthand for dependency-free rules, `services:` block only for the rule that needs `ReflectionProvider` injected.

### Added

- Laravel 13 coverage in the CI test matrix. `illuminate/support: ^11.31 | ^12.0 | ^13.0` was already declared; the matrix now exercises all three.
- Rule test coverage: 44 tests across 5 rule classes. Every rule has identifier assertions, dynamic-call-edge coverage, outside-`App`/`Tests` negative cases, and regression guards for the narrowing (unknown receiver, union types, user-defined facade, unannotated Laravel facade, non-Facade aliased class).
- Rector setup: `rector/rector ^2.0` dev dep, `rector.php` with `php83` set, import-name cleanup, and composer `rector`, `format`, `qa` scripts.
- `package-boost` + `orchestra/testbench` dev deps for managing `.ai/skills/` and injecting the verification-before-completion guideline block into `CLAUDE.md` / `AGENTS.md`.
- `CHANGELOG.md` backfilled to v0.1.0 in Keep-a-Changelog format, plus `update-changelog.yml` workflow that keeps it current on future releases.
- Laravel-package README: badges, per-rule docs, and cross-link to `hihaho/rector-rules`.

### CI

- Merged `rector.yml` + `fix-php-code-style-issues.yml` into a single `auto-fix.yml` mutator (`pull_request`-only, same-repo PRs only).
- New `update-changelog.yml` (runs on release publish).
- `analyzer.yml` and `tests.yml`: path filters, concurrency with cancel-in-progress, per-tool result caching, 5-minute timeouts, matrix-injection hardening via env vars.
- All third-party actions pinned to commit SHAs.

### Quality configs

- PHPStan: `strictRules.allRules`, 100% constant type coverage, PhpStorm `editorUrl`.
- Pint aligned with `hihaho/rector-rules` sibling.
- PHPUnit: `beStrictAboutTestsThatDoNotTestAnything` and a scoped `<source>` block.

## What's Changed

- Bump shivammathur/setup-php from 2.36.0 to 2.37.0 by @dependabot[bot] in https://github.com/hihaho/phpstan-rules/pull/43
- Package modernization by @SanderMuller in https://github.com/hihaho/phpstan-rules/pull/44

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v2.2.0...v3.0.0

## v2.2.0 - 2026-03-01

### Changed

- Fix bugs, improve performance, and expand test coverage ([#41](https://github.com/hihaho/phpstan-rules/pull/41))
- Harden GitHub Actions workflow security ([#42](https://github.com/hihaho/phpstan-rules/pull/42))
- Bump `actions/checkout` from 4 to 6 ([#40](https://github.com/hihaho/phpstan-rules/pull/40))

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v2.1.0...v2.2.0

## v2.1.0 - 2025-02-26

### Added

- Laravel 12 support ([#38](https://github.com/hihaho/phpstan-rules/pull/38))

### Changed

- Bump `shivammathur/setup-php` from 2.31.1 to 2.32.0 ([#37](https://github.com/hihaho/phpstan-rules/pull/37))

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v2.0.1...v2.1.0

## v2.0.1 - 2024-12-11

### Fixed

- Handle resource collections in `Rules/NamingClasses/EloquentApiResources` ([#36](https://github.com/hihaho/phpstan-rules/pull/36))

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v2.0.0...v2.0.1

## v2.0.0 - 2024-11-27

### Added

- PHP 8.4 support and upgrade to PHPStan 2.0 ([#34](https://github.com/hihaho/phpstan-rules/pull/34))

### Changed

- Bump `shivammathur/setup-php` 2.30.5 → 2.31.1 ([#26](https://github.com/hihaho/phpstan-rules/pull/26), [#27](https://github.com/hihaho/phpstan-rules/pull/27))
- Bump `actions/checkout` 4.1.7 → 4.2.2 ([#28](https://github.com/hihaho/phpstan-rules/pull/28), [#30](https://github.com/hihaho/phpstan-rules/pull/30), [#31](https://github.com/hihaho/phpstan-rules/pull/31))

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v1.1.1...v2.0.0

## v1.2.1 - 2024-12-11

### Fixed

- Handle resource collections in `Rules/NamingClasses/EloquentApiResources` ([#36](https://github.com/hihaho/phpstan-rules/pull/36))

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v1.2.0...v1.2.1

## v1.2.0 - 2024-12-11

### Changed

- Backport v2 changes (excluding the PHPStan 2.0 upgrade) to the v1 line.

## v1.1.1 - 2024-06-19

### Added

- Rule identifiers on all rule builders ([#25](https://github.com/hihaho/phpstan-rules/pull/25))

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v1.1.0...v1.1.1

## v1.1.0 - 2024-06-18

### Added

- Rules for debug statements in app, tests, and Blade ([#23](https://github.com/hihaho/phpstan-rules/pull/23))

### Changed

- Bump `actions/checkout` 4.1.4 → 4.1.7 ([#20](https://github.com/hihaho/phpstan-rules/pull/20), [#21](https://github.com/hihaho/phpstan-rules/pull/21), [#24](https://github.com/hihaho/phpstan-rules/pull/24))
- Bump `shivammathur/setup-php` 2.30.4 → 2.30.5 ([#22](https://github.com/hihaho/phpstan-rules/pull/22))

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v1.0.0...v1.1.0

## v1.0.0 - 2024-05-02

### Added

- Class-naming convention rules (commands, mailables, notifications, Eloquent API resources) ([#1](https://github.com/hihaho/phpstan-rules/pull/1))
- Rule: disallow facade aliases outside Blade ([#18](https://github.com/hihaho/phpstan-rules/pull/18))
- Laravel 11 support ([#19](https://github.com/hihaho/phpstan-rules/pull/19))
- Laravel Pint configuration ([#3](https://github.com/hihaho/phpstan-rules/pull/3))
- GitHub Actions CI ([#2](https://github.com/hihaho/phpstan-rules/pull/2))

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v0.1.0...v1.0.0

## v0.1.0 - 2023-09-20

### Added

- Rule: [Slash in URL](https://guidelines.hihaho.com/laravel.html#slash-in-url)
- Rule: [Route groups](https://guidelines.hihaho.com/laravel.html#route-groups)
- Rule: `NoInvadeInAppCode` — disallows `invade()` in the `App\` namespace and requires `spatie/invade` instead of `\Livewire\invade`
