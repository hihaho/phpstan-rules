# Hihaho PHPStan rules

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hihaho/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/hihaho/phpstan-rules)
[![Tests](https://img.shields.io/github/actions/workflow/status/hihaho/phpstan-rules/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hihaho/phpstan-rules/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/hihaho/phpstan-rules/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/hihaho/phpstan-rules/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/hihaho/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/hihaho/phpstan-rules)
[![License](https://img.shields.io/packagist/l/hihaho/phpstan-rules.svg?style=flat-square)](LICENSE.md)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/hihaho/phpstan-rules?style=flat)](https://packagist.org/packages/hihaho/phpstan-rules)

A set of PHPStan rules that enforce [Hihaho's Laravel guidelines](https://guidelines.hihaho.com/laravel.html)
at analyse time. They flag `invade()` calls in app code, facade aliases
outside Blade, stray debug helpers (`dump`, `dd`, `ray`, and friends)
left behind in production or test paths, and unvalidated request reads —
including `FormRequest` fields read outside the class's own `rules()`.

If you want the auto-fix counterparts for class-naming and route-group
conventions, see [`hihaho/rector-rules`](https://github.com/hihaho/rector-rules).

## Requirements

- PHP 8.3 or higher
- PHPStan 2.1 or higher
- Laravel 12.x or 13.x (via `illuminate/support`)

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

Four rules flag unvalidated request data. The first three flag reads from `Illuminate\Http\Request`; the fourth flags reading a field inside a `FormRequest` that the same class's `rules()` never validates. Use validated data instead: `$request->validated()`, `$request->safe()->string('key')`, or the array returned by `$request->validate([...])`.

| Rule                              | Targets                                                          | Identifier                                      |
|-----------------------------------|------------------------------------------------------------------|-------------------------------------------------|
| `NoUnsafeRequestDataRule`         | Method calls on `Request` / `FormRequest`                        | `hihaho.validation.noUnsafeRequestData`         |
| `NoUnsafeRequestHelperRule`       | `request('key')` helper with a literal arg                       | `hihaho.validation.noUnsafeRequestHelper`       |
| `NoUnsafeRequestFacadeRule`       | Static calls on `Illuminate\Support\Facades\Request`             | `hihaho.validation.noUnsafeRequestFacade`       |
| `UnvalidatedFormRequestFieldRule` | `$this->input('key')` inside a `FormRequest`, `key` ∉ `rules()`  | `hihaho.validation.unvalidatedFormRequestField` |

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

`UnvalidatedFormRequestFieldRule` covers that `$this`-inside-a-`FormRequest` exemption from the other side: it flags `$this->boolean('submit_redirect')` when `submit_redirect` is never declared in the same class's `rules()`. To stay high-precision it only resolves a literal `return [...]` array — a conditional, spread, `array_merge()`, returned variable, or a `rules()` it can't read statically makes the class opaque and skips it — and it skips any class that overrides `prepareForValidation()`, `validationData()`, or `all()` (including via a shared base or trait), since those rewrite the validated set. `rules()` declared on a base class is followed; nested keys match on their root segment, so a rule for `address.street` validates a read of `address`.

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

`UnvalidatedFormRequestFieldRule` reuses `noUnsafeRequestData.namespaces` / `excludeNamespaces` and carries its own list of single-key readers under `unvalidatedFormRequestField.accessors` (`input`, `get`, `query`, `post`, `string`, `str`, `integer`, `boolean`, `float`, `json`, `array`, `collect`, `date`, `enum`, `enums`, `file`); the full default is in `extension.neon`.

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

### Convention rules

Flag a bare `true`/`false`/`null` literal passed **positionally** as the last argument of a **first-party** method, nullsafe-method, static, or constructor call. A positional `setActive('name', false)` hides what the flag means; naming it — `setActive('name', active: false)` — makes the call self-documenting.

| Rule                                          | Targets                       | Identifier                                  |
|-----------------------------------------------|-------------------------------|---------------------------------------------|
| `PositionalFlagArgumentMethodCallRule`        | `$obj->method(..., true)`     | `hihaho.conventions.positionalFlagArgument` |
| `PositionalFlagArgumentNullsafeMethodCallRule`| `$obj?->method(..., true)`    | `hihaho.conventions.positionalFlagArgument` |
| `PositionalFlagArgumentStaticCallRule`        | `Klass::method(..., true)`    | `hihaho.conventions.positionalFlagArgument` |
| `PositionalFlagArgumentConstructorRule`       | `new Klass(..., true)`        | `hihaho.conventions.positionalFlagArgument` |

```php
namespace App\Services;

$toggle->setActive('name', false);          // reported — name the flag: active: false
$toggle?->setActive('name', false);         // reported
StaticFlag::toggle('name', false);          // reported
new Widget('name', true);                   // reported

$toggle->setActive('name', active: false);  // fine — already named
```

This pairs with rector-rules' `FirstPartyFlagArgumentToNamedRector`, which auto-fixes the flags it can resolve with bare PHPStan. Because PHPStan rules inherit the consumer's extensions, this rule flags the rest in a larastan-equipped app — including receivers (generic or inherited properties) that rector cannot resolve. rector rewrites; this rule gates.

Scope: the **last** argument only, and only when every argument is positional (no named or spread args); the matched parameter must be named and non-variadic. The parameter need **not** be bool-typed — a bare `null` on a `?Object` or `mixed` parameter is opaque too, matching the convention and the rector fixer (which names any bare flag without a type check). The gate is on the resolved member's **declaring** class, so an `App\` class inheriting a vendor method isn't flagged against vendor-declared, non-semver-stable parameter names. Callee namespaces are configurable:

```neon
parameters:
    positionalFlagArgument:
        firstPartyNamespaces:
            - App
            - Database\Factories
            - Tests
```

Param names aren't semver-stable in vendor code, so only first-party callees are flagged.

#### Named-argument manifest (opt-in producer)

rector-rules' `NamedArgumentFromManifestRector` names these flags at call sites whose receiver only resolves under larastan — the sites bare-PHPStan auto-fixers can't reach. It is inert without a JSON manifest, which this package can produce: include the opt-in extension and run analysis in your larastan-equipped project.

```neon
includes:
    - vendor/hihaho/phpstan-rules/named-argument-manifest.neon

parameters:
    namedArgumentManifest:
        firstPartyNamespaces:
            - App
            - Database\Factories
            - Tests
        outputPath: named-arguments-manifest.json
```

`vendor/bin/phpstan analyse` then writes `named-arguments-manifest.json` — the same detection emitted as records (`{file, line, method, argIndex, paramName, value}`) instead of errors, with no CI errors raised. It is a PHPStan Collector, not an error formatter, so it is independent of the gate rules and unaffected by your baseline (baselined sites still appear in the manifest).

`outputPath` may be nested (e.g. `.config/named-arguments-manifest.json`); the parent directory is created if it does not exist.

## Reflection extensions

### Stubbed methods

`StubbedMethodsClassReflectionExtension` teaches PHPStan about methods that exist at runtime but
not in reflection — Faker custom providers (added via `__call`), Laravel macros, facade
`__callStatic` forwarding. Without it, calls to these resolve to "undefined method" and have to be
baselined, which also hides genuine typos. With it, the configured methods resolve to their declared
return type, and a misspelled name (not in the configured set) still fails analysis.

It resolves nothing by default — each project declares its own methods via the `stubbedMethods`
parameter, a map of `class name => (method name => return type)`:

```neon
parameters:
    stubbedMethods:
        Faker\Generator:
            videoTimeInMilliseconds: int
            validPassword: string
            timestampsOfVideoClicks: 'array<int, int>'
        Illuminate\Testing\TestResponse:
            assertSeeLivewire: Illuminate\Testing\TestResponse
            fillForm: Illuminate\Testing\TestResponse
```

Return types are parsed with PHPStan's type-string resolver, so any valid PHPDoc type works
(`string`, `array<int, int>`, a class name for chainable assertions, etc.). Stubbed methods accept
any arguments, so only the method name and its return type are modelled — argument types are not
checked.

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
