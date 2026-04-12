# Changelog

All notable changes to `hihaho/phpstan-rules` will be documented in this file.

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
