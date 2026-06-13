# Changelog

All notable changes to `hihaho/phpstan-rules` will be documented in this file.

## v3.4.0 - 2026-06-13

<!-- verified-sha: 92b67121077aaad66856477d01229a655b3b32db -->
### Added

**Positional flag-argument rule** (`hihaho.conventions.positionalFlagArgument`) — flags a bare `true`/`false`/`null` literal passed **positionally** as the last argument of a **first-party** method, static, or constructor call. A positional `setActive('name', false)` hides what the flag means; naming it — `setActive('name', active: false)` — makes the call self-documenting. Three node types are covered: `PositionalFlagArgumentMethodCallRule`, `PositionalFlagArgumentStaticCallRule`, and `PositionalFlagArgumentConstructorRule`.

It pairs with [rector-rules'](https://github.com/hihaho/rector-rules) `FirstPartyFlagArgumentToNamedRector`: rector rewrites the flags it can resolve with bare PHPStan, and this rule flags the rest in a larastan-equipped app — including receivers (generic or inherited properties) that rector cannot resolve. rector rewrites; this rule gates.

Scope is deliberately tight in this first version: the last argument only, and only when every argument is positional (no named or spread args); the matched parameter must be `bool`/`?bool` and not variadic; and the gate is on the **declaring** class of the resolved member, so an `App\` class inheriting a vendor method is not flagged against vendor-declared parameter names. Callee namespaces are configurable under `positionalFlagArgument.firstPartyNamespaces` (default `App`, `Database\Factories`, `Tests`).

### Changed

**Dropped Laravel 11 support.** `illuminate/support` is now `^12.0||^13.0` (was `^11.31|^12.0|^13.0`) and `orchestra/testbench` is `^10.0||^11.0`. Laravel 11 can no longer be exercised on the test matrix: every `laravel/framework` v11 release carries an unpatched Packagist security advisory (CVE-2026-48019) with no patched v11 release, which Composer's advisory blocking rejects — so a Laravel 11 install no longer resolves.

This is **not a breaking change** for consumers. Tightening the constraint only narrows which release installs; Composer keeps a Laravel 11 project on the last compatible release (3.3.x) with no install error. Upgrade to Laravel 12 or 13 to receive this release.

### Tests

Suite: 103 tests / 131 assertions. The new rule ships with method/static/constructor coverage including the precision guards (non-bool nullable parameters, inherited vendor methods, variadic parameters, named and spread arguments).

### Notes

The flag rule fires on a pattern not previously analysed, so on an existing codebase it will surface new findings. Generate a baseline (`vendor/bin/phpstan analyse --generate-baseline`) and work it down — each hit is a bare flag that reads better named.

No public API or configuration removed. Update in place (or stay on 3.3.x if you are still on Laravel 11).

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v3.3.0...v3.4.0

## v3.3.0 - 2026-06-13

<!-- verified-sha: d3a9e1685ad4e9f746619f26c60f794e1d64c3ee -->
### Added

**`UnvalidatedFormRequestFieldRule`** (`hihaho.validation.unvalidatedFormRequestField`) — flags reading a request field inside a `FormRequest` when that field's key is never declared in the same class's `rules()`. It is the inverse of `NoUnsafeRequestDataRule`: the existing request rules exempt `$this` reads inside a `Request` subclass, and this rule covers that gap from the other side. A `FormRequest` that reads `$this->boolean('submit_redirect')` while `rules()` only validates other keys silently consumes unvalidated input; this catches it at analyse time.

Sourced from real-world adoption — the densest recurring review nit on `FormRequest`-heavy controllers.

The rule is high-precision and bails to a skip rather than risk a false positive:

- It resolves only a literal `return [...]` array in `rules()`. A conditional return, a spread, `array_merge()`, a returned variable, or any non-literal key marks the class opaque and skips it.
- It skips any class that overrides `prepareForValidation()`, `validationData()`, or `all()` anywhere in its hierarchy (including a shared base class or trait), since those rewrite the validated data. Framework defaults inherited by every `FormRequest` are not treated as overrides.
- `rules()` inherited from a base class is followed to where it is declared; reads from a trait method are resolved against the using class.
- Nested keys match on their root segment, so a rule for `address.street` validates a read of `address`.

The accessor list — the single-key readers it inspects (`input`, `get`, `query`, `post`, `string`, `str`, `integer`, `boolean`, `float`, `json`, `array`, `collect`, `date`, `enum`, `enums`, `file`) — is configurable under `unvalidatedFormRequestField.accessors`. The rule reuses `noUnsafeRequestData.namespaces` / `excludeNamespaces` for scoping. It is registered through the combined single-dispatch method-call rule, so it adds no extra per-node dispatch overhead.

### Tests

- `tests/Rules/Validation/UnvalidatedFormRequestFieldRuleTest.php` with 19 cases and 18 fixtures covering: flagged unvalidated reads across every accessor, validated and root-segment-validated keys, empty `rules()`, a missing `rules()` method, conditional / `array_merge` / variable / spread `rules()`, an overridden `prepareForValidation()` (direct and inherited from a base), inherited base-class `rules()`, a read from a trait method, dynamic keys, a same-named method on a non-`FormRequest`, and out-of-namespace classes.

Suite: 96 tests / 122 assertions.

### Notes

The rule is opt-in only in the sense that it fires on a previously unanalysed pattern; on existing codebases it will surface new errors for `FormRequest` fields read outside `rules()`. Generate a baseline (`vendor/bin/phpstan analyse --generate-baseline`) and work it down — each hit is either a missing rule, a typo'd key, or a field that should be validated.

No public API or configuration removed. Update in place.

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v3.2.0...v3.3.0

## v3.2.0 - 2026-06-09

<!-- verified-sha: c625a78716405a255caef2bf1f05153715b08bb8 -->
### Performance

Rule dispatch overhead is now negligible (~0ms) across all three node types.

Previously each PHPStan node visit dispatched to multiple independent rule classes. This release merges related rules into three combined rules — one per node type — so PHPStan pays a single dispatch cost regardless of how many checks run on that node.

| Node type | Before | After |
|-----------|--------|-------|
| `FuncCall` | 3 rule classes dispatched | 1 (`CombinedFuncCallRule`) |
| `MethodCall` | 2 rule classes dispatched | 1 (`CombinedMethodCallRule`) |
| `StaticCall` | 3 rule classes dispatched | 1 (`CombinedStaticCallRule`) |

Additional micro-optimisations applied across the board:

- Early namespace checks placed before any reflection or type-resolution calls
- `in_array` hot-path lookups replaced with `isset` on pre-built hash maps
- Facade class reflection cached per constructor (StaticChainedNoDebugInNamespaceRule)
- `ObjectType` comparison result cached per class name (NoUnsafeRequestDataRule)
- Static `ReflectionClass` cache added (OnlyAllowFacadeAliasInBlade)
- `->name` direct property access used instead of `->toString()` where applicable
- `getLast()` pre-filter added to skip `strtolower` on non-matching calls (NoUnsafeRequestFacadeRule)

All rule identifiers, configuration parameters, and public API are unchanged — this is a drop-in upgrade.

### Internal

- Deduplicated `isDebugHelperMethodCall` by moving it to `BaseNoDebugRule`; `FUNCTION_DEBUG_STATEMENTS` and `METHOD_DEBUG_STATEMENTS` promoted to `protected` so combined rules derive quick-reject lookups from the single authoritative source.
- CI: `L^11.31` matrix legs marked `continue-on-error` while Packagist security advisories on all `laravel/framework` v11 releases prevent `orchestra/testbench v9.x` from resolving. Laravel 12 and 13 legs are unaffected and remain the authoritative CI gate.
- Migrated from `sandermuller/package-boost` to `sandermuller/package-boost-php ^1.0` + `sandermuller/boost-skills ^2.4`.

### What's Changed

* Update CHANGELOG for v3.1.1 and v3.1.2 by @SanderMuller in https://github.com/hihaho/phpstan-rules/pull/46
* Bump peter-evans/create-pull-request from 7.0.8 to 8.1.1 by @dependabot[bot] in https://github.com/hihaho/phpstan-rules/pull/47
* Update sandermuller/package-boost requirement from ^0.9 to ^0.11 by @dependabot[bot] in https://github.com/hihaho/phpstan-rules/pull/48
* Update sandermuller/package-boost requirement from ^0.11 to ^0.15 by @dependabot[bot] in https://github.com/hihaho/phpstan-rules/pull/49
* Bump shivammathur/setup-php from 2.37.0 to 2.37.1 by @dependabot[bot] in https://github.com/hihaho/phpstan-rules/pull/50

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v3.1.2...v3.2.0

## v3.1.2 - 2026-04-22

Internal performance work on rule hot paths — every optimisation is strictly a filter-order or data-structure change. No behaviour change, no public API change, no new or removed errors, no new configuration keys. All five rules remain `final readonly class`.

- **`NoUnsafeRequestHelperRule`** — short-circuit the `ReflectionProvider::hasFunction` / `getFunction` pair with a `strtolower($node->name->getLast()) !== 'request'` pre-check. Reflection used to fire on every `FuncCall` in the configured namespaces; it now fires only on calls whose last name-segment could actually resolve to the global `request()` helper. Alias-aware: PHPStan's `NameResolver` already rewrites `use function request as X` imports to `FullyQualified('request')` before the rule runs, so `X('key')` still flags. Locked with a new `use function request as req` regression stub.
- **`NoUnsafeRequestDataRule`** — `in_array(strtolower($method), $listOf22, true)` replaced by `isset($lookup[strtolower($method)])` against a flipped `array<string, true>` built once in the constructor. `classIsRequest` no longer reconstructs `new ObjectType(Request::class)` on every call; it reuses a single instance hoisted to a private readonly property.
- **`NoUnsafeRequestFacadeRule`** — same `isset`-map treatment for unsafe methods; `strtolower(Illuminate\Support\Facades\Request::class)` hoisted to a private readonly property instead of recomputed per call; and the class-equality check (one string compare) now bails before the method-name lookup. Locked with a new `use Illuminate\Support\Facades\Request as RequestFacade` regression stub.
- **`NoInvadeInAppCode`** — `$node->name->toString()` is computed once into a local and reused across the two equality checks instead of being rebuilt twice.

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v3.1.1...v3.1.2

## v3.1.1 - 2026-04-22

## What's Changed

* Fix update-changelog workflow + backfill v3.1.0 entry by @SanderMuller in https://github.com/hihaho/phpstan-rules/pull/45

**Full Changelog**: https://github.com/hihaho/phpstan-rules/compare/v3.1.0...v3.1.1

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
