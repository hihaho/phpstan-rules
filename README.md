# Hihaho PHPStan rules

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hihaho/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/hihaho/phpstan-rules)
[![Tests](https://img.shields.io/github/actions/workflow/status/hihaho/phpstan-rules/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hihaho/phpstan-rules/actions/workflows/tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/hihaho/phpstan-rules/analyzer.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/hihaho/phpstan-rules/actions/workflows/analyzer.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/hihaho/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/hihaho/phpstan-rules)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/hihaho/phpstan-rules?style=flat)](https://packagist.org/packages/hihaho/phpstan-rules)

A set of PHPStan rules that enforce [Hihaho's Laravel guidelines](https://guidelines.hihaho.com/laravel.html)
at analyse time. They flag `invade()` calls in app code, facade aliases
outside Blade, stray debug helpers (`dump`, `dd`, `ray`, and friends)
left behind in production or test paths, and unvalidated reads from
`Illuminate\Http\Request`.

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

### `NoUnsafeRequestDataRule`

Forbids reading unvalidated input from `Illuminate\Http\Request` (including
`FormRequest` subclasses) in application code. Use the return value of
`validated()`, `safe()`, or `validate([...])` — never raw readers like
`input()`, `all()`, `get()` on the request object.

`FormRequest` auto-validation runs on dispatch, but raw readers inherited
from `Request` still return the full payload, including keys outside
`rules()`. This rule closes that gap.

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

final class StoreUserController
{
    public function __invoke(Request $request): mixed
    {
        $request->input('name');              // reported
        $request->all();                      // reported
        $request->safe()->input('name');      // fine
        return $request->validate(['name' => 'required']);
    }
}
```

Reads from `$this` inside a request class (`Illuminate\Http\Request` or
subclass, including `FormRequest`) are intentionally allowed — that is
where validation pulls its source data. The scope-class exemption uses
PHPStan's inheritance resolution, so custom base classes work
transparently — e.g. `App\Http\Requests\FormRequest extends BaseFormRequest
extends Illuminate\Foundation\Http\FormRequest` is exempted without
additional configuration.

Out of scope: ArrayAccess (`$request['x']`) and magic property access
(`$request->x`). Static facade calls are covered by `NoUnsafeRequestFacadeRule`
(below).

Identifier: `hihaho.validation.noUnsafeRequestData`

Configuration (override in your `phpstan.neon`):

```neon
parameters:
    noUnsafeRequestData:
        namespaces:
            - App          # which root namespaces to enforce in
        excludeNamespaces:
            - App\Providers         # Laravel bootstrap area (default)
            - App\Http\Responses    # Fortify / response contracts (default)
            - App\Http\Resources    # add your own exclusions
        unsafeMethods:
            - input        # full default list is in extension.neon
            - all
            - get
            # ...
```

`excludeNamespaces` defaults to `['App\Providers', 'App\Http\Responses']`
because:

- **`App\Providers`** — Laravel bootstrap code (`RateLimiter::for(...)`
  throttle closures, service bindings, Fortify response registrations)
  receives the raw `Request` by framework design and has no FormRequest
  entry point.
- **`App\Http\Responses`** — Fortify / auth response classes implement
  contract-dictated signatures like
  `LoginResponse::toResponse(Request $request)`. Signature is fixed by
  the interface; no validation boundary inside the class.

Common add-on depending on your project:

- **`App\Http\Resources`** — `JsonResource::toArray(Request $request)` is
  framework-dictated, but whether a resource should read the raw request
  at all is a legitimate architectural debate. Not defaulted; add it if
  your team accepts the pattern.

### `NoUnsafeRequestHelperRule`

Companion to `NoUnsafeRequestDataRule` covering the direct-argument form of
Laravel's `request()` helper. `request('key')` returns raw input and
bypasses validation entirely. Chained forms like `request()->input('key')`
are already caught by `NoUnsafeRequestDataRule`.

```php
namespace App\Http\Controllers;

final class FetchController
{
    public function __invoke(): mixed
    {
        return request('id');  // reported
    }
}
```

Zero-argument `request()` is not flagged — any method call on its return
value is caught by `NoUnsafeRequestDataRule`.

Shares the `namespaces` configuration with `NoUnsafeRequestDataRule`.

Identifier: `hihaho.validation.noUnsafeRequestHelper`

### `NoUnsafeRequestFacadeRule`

Companion rule covering static calls on the `Illuminate\Support\Facades\Request`
facade. `Request::input('x')`, `Request::boolean('debug')`, etc. bypass both
the instance-method and helper-function rules because they are `StaticCall`
nodes against a different receiver.

```php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Request;

final class DebugController
{
    public function __invoke(): bool
    {
        return Request::boolean('debug');  // reported
    }
}
```

Only matches the Laravel request facade — static calls on
`Illuminate\Http\Request` itself (e.g. `Request::capture()`) are not
flagged because they do not return raw input.

Shares `namespaces` and `unsafeMethods` with `NoUnsafeRequestDataRule`.

Identifier: `hihaho.validation.noUnsafeRequestFacade`

### Expected baseline categories

On first adoption in a non-trivial Laravel codebase these rules will flag
a nonzero baseline. Some patterns are legitimately caught architecture
smells (models reading `$request->input()`, domain calculations gating on
`Request::boolean('debug')`, etc.); others are framework conventions where
raw access is unavoidable. Expect these to stay in your baseline:

- **Dynamic-key admin CRUD.** Bulk-edit controllers that loop over
  data-driven field registries: `$request->collect('fields')->each(...)`,
  `$request->input($dynamicKey)`. Keys aren't known at design time, so
  there is no ergonomic FormRequest equivalent.
- **Pre-validation framework callbacks.** `RateLimiter::for('login',
  fn (Request $request) => $request->input(...))` runs before any
  FormRequest dispatch by design.
- **Fortify response contracts.** Classes implementing
  `Laravel\Fortify\Contracts\*Response` receive the raw `Request`.
- **`JsonResource::toArray(Request $request)`.** Resources receive the
  current request by Laravel convention; validation happened upstream
  but is not statically provable.

These are expected baseline territory, not rule bugs. Baseline them on
adoption and drive the remainder to zero as a separate cleanup.

For the dynamic-key CRUD case where no FormRequest equivalent is
ergonomic, suppress inline instead of adding to the baseline:

```php
foreach ($schema->fields() as $field) {
    // @phpstan-ignore hihaho.validation.noUnsafeRequestData
    $value = $request->input($field->key);
    // ...
}
```

Inline `@phpstan-ignore` keeps the suppression next to the rationale and
surfaces it if the surrounding code changes.

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
