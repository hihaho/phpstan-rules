# Hihaho PHPStan rules

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hihaho/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/hihaho/phpstan-rules)
[![Tests](https://img.shields.io/github/actions/workflow/status/hihaho/phpstan-rules/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hihaho/phpstan-rules/actions/workflows/tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/hihaho/phpstan-rules/analyzer.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/hihaho/phpstan-rules/actions/workflows/analyzer.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/hihaho/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/hihaho/phpstan-rules)

A set of PHPStan rules that enforce [Hihaho's Laravel guidelines](https://guidelines.hihaho.com/laravel.html)
at analyse time. They flag `invade()` calls in app code, facade aliases
outside Blade, and stray debug helpers (`dump`, `dd`, `ray`, and friends)
left behind in production or test paths.

If you want the auto-fix counterparts for class-naming and route-group
conventions, see [`hihaho/rector-rules`](https://github.com/hihaho/rector-rules).

## Requirements

- PHP 8.3 or higher
- PHPStan 2.1 or higher
- Laravel 11.31, 12.x, or 13.x (via `illuminate/support`)

## Installation

```bash
composer require --dev hihaho/phpstan-rules
```

If you have [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer),
that's it. The rules register themselves.

Without it, include the extension in your `phpstan.neon`:

```neon
includes:
    - vendor/hihaho/phpstan-rules/extension.neon
```

## Rules

### `NoInvadeInAppCode`

Flags [`invade()`](https://github.com/spatie/invade) calls inside `App\`.
`invade` is a test helper for reaching into private state; it has no place
in production code. Also flags `\Livewire\invade()` in any namespace; if
you need `invade`, use the global one from `spatie/invade`.

```php
namespace App\Services;

invade($user)->privateMethod(); // reported
```

Identifiers: `hihaho.generic.noInvadeInAppCode`,
`hihaho.generic.disallowedUsageOfLivewireInvade`

### `OnlyAllowFacadeAliasInBlade`

Short facade aliases belong in Blade. In PHP, use the fully qualified facade
so imports stay explicit.

```php
use Route;                            // reported
use Illuminate\Support\Facades\Route; // fine
```

Identifier: `hihaho.generic.onlyAllowFacadeAliasInBlade`

### Debug rules

Three rules that together keep debug calls out of `App\` and `Tests\`:

| Rule                                  | Targets                         | Examples                                                      |
|---------------------------------------|---------------------------------|---------------------------------------------------------------|
| `NoDebugInNamespaceRule`              | Global debug functions          | `dump()`, `dd()`, `ddd()`, `ray()`, `print_r()`, `var_dump()` |
| `ChainedNoDebugInNamespaceRule`       | Method chains on Laravel types  | `collect()->dump()`, `$builder->dd()`                         |
| `StaticChainedNoDebugInNamespaceRule` | Static calls on Laravel facades | `Http::dump()`, `Cache::dd()`                                 |

The chained and static rules use PHPStan reflection to narrow matches:
they only flag methods declared by (or proxied through) the `Illuminate\`
namespace, so your own domain classes with a `->dump()` method stay clean.

Identifiers: `hihaho.debug.noDebugIn{App,Tests}`,
`hihaho.debug.noChainedDebugIn{App,Tests}`,
`hihaho.debug.noStaticChainedDebugIn{App,Tests}`

## Testing

```bash
composer test
```

Before opening a PR, run the full pipeline (Pint, Rector, PHPStan, tests):

```bash
composer qa
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Please email [security@hihaho.com](mailto:security@hihaho.com) instead of
filing a public issue.

## Credits

- [Hihaho](https://github.com/hihaho)
- [All contributors](https://github.com/hihaho/phpstan-rules/contributors)

## License

MIT. See [LICENSE.md](LICENSE.md).
