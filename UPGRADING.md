# Upgrade Guide

Migration notes for each major-version bump of `hihaho/phpstan-rules`.

## Upgrading from 3.x to 4.0

### New rules: `NoUnsafeRequestDataRule`, `NoUnsafeRequestHelperRule`, and `NoUnsafeRequestFacadeRule`

**Likelihood of impact: high**

Three new rules flag unvalidated reads from `Illuminate\Http\Request`:

- `NoUnsafeRequestDataRule` reports `MethodCall`s on a `Request` or
  `FormRequest` receiver whose method is in `noUnsafeRequestData.unsafeMethods`
  (defaults include `input`, `all`, `get`, `query`, `only`, `string`,
  `integer`, `boolean`, `collect`, `fluent`, `enum`, and others — see
  `extension.neon` for the full list).
- `NoUnsafeRequestHelperRule` reports `request('key')` (direct-argument
  form) — the chained form `request()->input('key')` is caught by the
  first rule.
- `NoUnsafeRequestFacadeRule` reports static calls on
  `Illuminate\Support\Facades\Request` (e.g. `Request::input('x')`,
  `Request::boolean('debug')`).

Both rules run only inside namespaces configured by
`noUnsafeRequestData.namespaces` (default: `App`). Raw reads inside a
class that is-a `Illuminate\Http\Request` (e.g. your `FormRequest`
subclasses' `rules()`/`prepareForValidation()` methods) are allowed.

Note: a `FormRequest` typehint triggers auto-validation, but raw readers
inherited from `Request` still return the full payload — including keys
outside `rules()`. Use `$request->validated()` / `$request->safe()` or
the return value of `$request->validate([...])` instead.

**Migrating existing code**

Replace raw reads with validated accessors:

```diff
 public function __invoke(StoreUserRequest $request): JsonResponse
 {
-    $name = $request->input('name');
-    $age = $request->integer('age');
+    $data = $request->validated();
+    $name = $data['name'];
+    $age = $data['age'];
 }
```

**Adjusting the policy**

Override the defaults in `phpstan.neon` if needed:

```neon
parameters:
    noUnsafeRequestData:
        namespaces:
            - App
            - Domain
        unsafeMethods:
            - input
            - all
            - get
```

**Suppressing per call site**

Identifiers: `hihaho.validation.noUnsafeRequestData`,
`hihaho.validation.noUnsafeRequestHelper`,
`hihaho.validation.noUnsafeRequestFacade`. All are ignorable:

```php
// @phpstan-ignore hihaho.validation.noUnsafeRequestData
$legacyPayload = $request->all();
```

**Known limitations (not flagged)**

- ArrayAccess: `$request['key']`.
- Magic property access: `$request->key`.
- Dynamic method calls: `$request->{$method}()`.
- Receivers with no known object type (e.g. `mixed`, `object`).

## Upgrading from 2.x to 3.0

### PHP 8.3 required

**Likelihood of impact: high**

Minimum PHP is now `^8.3`. PHP 8.2 has been in security-only support since December 2024.

Bump your project's PHP constraint:

```json
"require": {
    "php": "^8.3"
}
```

If you cannot upgrade yet, stay on `hihaho/phpstan-rules:^2.2`. There is no planned `v2.x` maintenance branch; `v3.x` is the only line that gets new releases.

### Class-naming and routing rules moved to `hihaho/rector-rules`

**Likelihood of impact: high**

Six rules are no longer in this package:

| Removed                                    | Replaced by (in [`hihaho/rector-rules`](https://github.com/hihaho/rector-rules)) |
|--------------------------------------------|----------------------------------------------------------------------------------|
| `Rules\NamingClasses\Commands`             | `AddCommandSuffixRector`                                                         |
| `Rules\NamingClasses\Mail`                 | `AddMailSuffixRector`                                                            |
| `Rules\NamingClasses\Notifications`        | `AddNotificationSuffixRector`                                                    |
| `Rules\NamingClasses\EloquentApiResources` | `AddResourceSuffixRector`                                                        |
| `Rules\Routing\SlashInUrl`                 | `NormalizeRoutePathRector`                                                       |
| `Rules\Routing\RouteGroups`                | `RouteGroupArrayToMethodsRector`                                                 |

The rector-rules replacements enforce the same conventions and can rewrite offending code in place.

If you want to keep these conventions enforced, install and configure Rector. Installing the package alone does nothing: Rector has to be configured and invoked.

```bash
composer require --dev rector/rector hihaho/rector-rules
```

```php
// rector.php
<?php declare(strict_types=1);

use Hihaho\RectorRules\Set\HihahoSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/app', __DIR__ . '/routes'])
    ->withSets([HihahoSetList::ALL]);
```

Then run locally:

```bash
vendor/bin/rector process
```

And add the same command to CI. Use `--dry-run` for read-only PR checks, non-dry for auto-fix. Without this, the conventions are silently unenforced.

### Remove stale suppressions and baseline entries

**Likelihood of impact: high** (only if you suppress PHPStan errors by identifier or use a baseline)

PHPStan's default `reportUnmatchedIgnoredErrors: true` turns stale ignores into errors. Remove any suppressions for identifiers that are no longer emitted:

- `hihaho.naming.classes.Command`
- `hihaho.naming.classes.Mailable`
- `hihaho.naming.classes.Notification`
- `hihaho.naming.classes.eloquentApiResources`
- `hihaho.naming.classes.eloquentApiResourceCollections`
- `hihaho.routing.noEmptyPath`
- `hihaho.routing.noLeadingOrTrailingSlashInUrl`
- `hihaho.routing.routeGroups`

If you use a baseline file (`phpstan-baseline.neon`), regenerate it after the upgrade:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

### `NoInvadeInAppCode` identifier category changed

**Likelihood of impact: medium** (only if you suppress these by identifier)

Both identifiers this rule emits moved from `hihaho.debug.*` to `hihaho.generic.*`:

| Before                                         | After                                            |
|------------------------------------------------|--------------------------------------------------|
| `hihaho.debug.noInvadeInAppCode`               | `hihaho.generic.noInvadeInAppCode`               |
| `hihaho.debug.disallowedUsageOfLivewireInvade` | `hihaho.generic.disallowedUsageOfLivewireInvade` |

Update any identifier-based suppressions in `phpstan.neon`:

```diff
 ignoreErrors:
     -
-        identifier: hihaho.debug.noInvadeInAppCode
+        identifier: hihaho.generic.noInvadeInAppCode
         paths:
             - app/SomeSpecificException.php
```

Inline `@phpstan-ignore` and `@phpstan-ignore-next-line` directives need the same rename:

```diff
-// @phpstan-ignore hihaho.debug.noInvadeInAppCode
+// @phpstan-ignore hihaho.generic.noInvadeInAppCode
 invade($model)->privateMethod();
```

The error message text is unchanged, so path-based and message-based suppressions still work without changes.

### Debug rule detection narrowed

**Likelihood of impact: low**

`ChainedNoDebugInNamespaceRule` and `StaticChainedNoDebugInNamespaceRule` now require the method to resolve to a class in the `Illuminate\` namespace (or a `Facade` subclass for static calls). Previously any `->dump()`, `->dd()`, `->ddd()`, or `->ray()` method call or static facade call would flag regardless of receiver type.

Some previously-reported calls on non-Illuminate types will stop being flagged. Re-run PHPStan after upgrading and check that any calls that now pass analysis are not actual debug leftovers you were relying on the rule to catch.

This closes a common false-positive source: domain classes with their own `->dump()` method (e.g. a `QueryCollector` value object) no longer trigger the rule.

### Drop redundant `illuminate/*` dev deps

**Likelihood of impact: none** (only affects this package's own `composer.json`, not consumers)

If you were extending this package internally and mirrored its `require-dev` block, you can now drop `illuminate/console`, `illuminate/http`, `illuminate/mail`, `illuminate/notifications`, and `illuminate/routing`. None of the remaining rules depend on them. `illuminate/support` is still required.
