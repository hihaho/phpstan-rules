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

### Request-validation rules

Three rules flag unvalidated reads from `Illuminate\Http\Request`. Use validated data instead: `$request->validated()`, `$request->safe()->string('key')`, or the array returned by `$request->validate([...])`.

| Rule                        | Targets                                              | Identifier                                |
|-----------------------------|------------------------------------------------------|-------------------------------------------|
| `NoUnsafeRequestDataRule`   | Method calls on `Request` / `FormRequest`            | `hihaho.validation.noUnsafeRequestData`   |
| `NoUnsafeRequestHelperRule` | `request('key')` helper with a literal arg           | `hihaho.validation.noUnsafeRequestHelper` |
| `NoUnsafeRequestFacadeRule` | Static calls on `Illuminate\Support\Facades\Request` | `hihaho.validation.noUnsafeRequestFacade` |

`FormRequest` auto-validation runs on dispatch, but inherited readers still return the full payload including keys outside `rules()`, so they're flagged on `FormRequest` too. Chained `request()->input('x')` is caught by the Data rule because the receiver resolves to `Request`. Zero-argument `request()` is not flagged.

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;

final class StoreUserController
{
    public function __invoke(Request $request): mixed
    {
        $request->input('name');              // reported (data)
        request('id');                        // reported (helper)
        RequestFacade::boolean('debug');      // reported (facade)

        $request->safe()->string('name');     // fine

        return $request->validate(['name' => 'required']);
    }
}
```

Reads from `$this` inside a `Request` subclass, including your own `FormRequest` bases, are exempted. The scope-class check walks the inheritance chain, so a custom `App\Http\Requests\FormRequest extends BaseFormRequest extends Illuminate\Foundation\Http\FormRequest` works without extra config. Static calls on `Illuminate\Http\Request` itself (e.g. `Request::capture()`) aren't flagged; they don't return raw input.

Out of scope: ArrayAccess (`$request['x']`), magic property access (`$request->x`), and Symfony `InputBag` property access (`$request->query->get('x')`, `->headers->get()`, `->cookies->get()`). The InputBag path is legitimate for raw header or cookie reads, but flag it in code review so it doesn't turn into a de-facto suppression channel.

#### Configuration

```neon
parameters:
    noUnsafeRequestData:
        namespaces:
            - App
        excludeNamespaces:
            - App\Providers         # Laravel bootstrap (default)
            - App\Http\Responses    # Fortify response contracts (default)
            # - App\Http\Resources  # opt-in: accept toArray(Request) reads
        unsafeMethods:
            # full default list in extension.neon
            - input
            - all
            - get
```

`App\Providers` and `App\Http\Responses` are default-excluded because the signatures there come from the framework (`RateLimiter::for(...)` closures, `LoginResponse::toResponse(Request)`) and there's no FormRequest to route the data through. `App\Http\Resources` is opt-in. Whether a resource should read raw request is a team call.

#### Adopting on an existing codebase

First-run baselines are nonzero. Generate one and work it down over multiple PRs:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

Patterns that will stay baselined (the rule can't help with them):

- Dynamic-key admin CRUD. Bulk-edit controllers looping over `$request->collect('fields')->each(...)` with schema-driven keys. Suppress inline:
  ```php
  // @phpstan-ignore hihaho.validation.noUnsafeRequestData
  $value = $request->input($field->key);
  ```
- Pre-validation framework callbacks. Already covered by the `App\Providers` default exclusion.
- Fortify response contracts. Already covered by the `App\Http\Responses` default exclusion.
- `JsonResource::toArray(Request)`. Add `App\Http\Resources` to `excludeNamespaces` if you accept the pattern.

Safe-swap yield on the first triage runs 2-10% from field data: calls already validated inline where the flagged key is in the rules, plus FormRequest cases where the flagged key is in `rules()` and migrates to `$request->safe()->string(...)` or `$request->validated()`. The rest needs judgment. Your options are to introduce a FormRequest, extend existing rules to cover the flagged key, push validation upstream, or refactor the surrounding code. Plan on several PRs over weeks, not a one-time sweep.

Common traps:

- "Injected a FormRequest, so I'm safe." The rule fires when the FormRequest has no `rules()` (auth-only wrappers) or has rules that don't cover the flagged key. Check `rules()` before assuming it's a false positive.
- `validated()` drops keys not in `rules()`, nested props included. Reading `$request->input('interactions.$.foo')` won't migrate if only `interactions` is in `rules()`. You'll need nested rules first.
- LLM agents are unreliable for bulk triage on this rule. Reliable categorization needs AST inspection that intersects `validate()` rule keys with flagged keys; one adopter's agent caught 1 of 5 candidates. Use human review or a Rector pass.
- Livewire and Filament projects handle input through component props and form schemas, outside these rules' node targets. A low hit count is a structural fact, not proof of cleanliness. Review `mount()` and form-submit paths separately.

Rule hits in `Support` or utility namespaces often point at dead code. Grep the call graph before adding to the baseline; the fix may be a delete.

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
