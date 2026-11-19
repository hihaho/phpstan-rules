# Hihaho PHPStan rules

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hihaho/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/hihaho/phpstan-rules)
[![Tests](https://img.shields.io/github/actions/workflow/status/hihaho/phpstan-rules/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hihaho/phpstan-rules/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/hihaho/phpstan-rules/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/hihaho/phpstan-rules/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/hihaho/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/hihaho/phpstan-rules)
[![License](https://img.shields.io/packagist/l/hihaho/phpstan-rules.svg?style=flat-square)](LICENSE.md)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/hihaho/phpstan-rules?style=flat)](https://packagist.org/packages/hihaho/phpstan-rules)

Code review keeps catching the same Laravel mistakes: a `dd()` left in a controller, a request field read without validation, an index added to a huge table inside a deploy. These PHPStan rules report them at analyse time, following [Hihaho's Laravel guidelines](https://guidelines.hihaho.com/laravel.html).

For auto-fixes of class-naming and route-group conventions, see [`hihaho/rector-rules`](https://github.com/hihaho/rector-rules).

## Requirements

- PHP 8.4 or higher
- PHPStan 2.1.8 or higher
- Laravel 12.x or 13.x (via `illuminate/support` and `illuminate/database`)

## Installation

```bash
composer require --dev hihaho/phpstan-rules
```

With [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer) the rules register themselves. Without it, include the extension in your `phpstan.neon`:

```neon
includes:
    - vendor/hihaho/phpstan-rules/extension.neon
```

## Rules

### `NoInvadeInAppCode`

Flags [`invade()`](https://github.com/spatie/invade) calls inside `App\`: it is a test helper for reaching into private state. Also flags `\Livewire\invade()` in any namespace; use the global one from `spatie/invade`.

```php
namespace App\Services;

invade($user)->privateMethod(); // reported
```

Identifiers: `hihaho.generic.noInvadeInAppCode`,
`hihaho.generic.disallowedUsageOfLivewireInvade`

### `OnlyAllowFacadeAliasInBlade`

Short facade aliases belong in Blade. In PHP, import the fully qualified facade.

```php
use Route;                            // reported
use Illuminate\Support\Facades\Route; // fine
```

Identifier: `hihaho.generic.onlyAllowFacadeAliasInBlade`

### Debug rules

Three rules keep debug calls out of `App\` and `Tests\`:

| Rule                                  | Targets                         | Examples                                                      |
|---------------------------------------|---------------------------------|---------------------------------------------------------------|
| `NoDebugInNamespaceRule`              | Global debug functions          | `dump()`, `dd()`, `ddd()`, `ray()`, `print_r()`, `var_dump()` |
| `ChainedNoDebugInNamespaceRule`       | Method chains on Laravel types  | `collect()->dump()`, `$builder->dd()`                         |
| `StaticChainedNoDebugInNamespaceRule` | Static calls on Laravel facades | `Http::dump()`, `Cache::dd()`                                 |

The chained and static rules only flag methods declared by (or proxied through) `Illuminate\`, so your own `->dump()` method stays clean.

Identifiers: `hihaho.debug.noDebugIn{App,Tests}`,
`hihaho.debug.noChainedDebugIn{App,Tests}`,
`hihaho.debug.noStaticChainedDebugIn{App,Tests}`

### Request-validation rules

Four rules flag unvalidated request data. Use validated data instead: `$request->validated()`, `$request->safe()->string('key')`, or the array `$request->validate([...])` returns.

| Rule                              | Targets                                                          | Identifier                                      |
|-----------------------------------|------------------------------------------------------------------|-------------------------------------------------|
| `NoUnsafeRequestDataRule`         | Method calls on `Request` / `FormRequest`                        | `hihaho.validation.noUnsafeRequestData`         |
| `NoUnsafeRequestHelperRule`       | `request('key')` helper with a literal arg                       | `hihaho.validation.noUnsafeRequestHelper`       |
| `NoUnsafeRequestFacadeRule`       | Static calls on `Illuminate\Support\Facades\Request`             | `hihaho.validation.noUnsafeRequestFacade`       |
| `UnvalidatedFormRequestFieldRule` | `$this->input('key')` inside a `FormRequest`, `key` ∉ `rules()`  | `hihaho.validation.unvalidatedFormRequestField` |

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

- Reads on a `FormRequest` are flagged too: its inherited readers still return keys outside `rules()`.
- Reads from `$this` inside a `Request` subclass, including your own `FormRequest` bases, are exempt. `UnvalidatedFormRequestFieldRule` covers that exemption from the other side: it flags `$this->boolean('submit_redirect')` when `rules()` never declares `submit_redirect`.
- `UnvalidatedFormRequestFieldRule` only reads a literal `return [...]` from `rules()`, including one on a base class; a nested rule key such as `address.street` covers a read of `address`. A `rules()` it cannot read statically, or a class that overrides `prepareForValidation()`, `validationData()` or `all()`, is skipped.
- Not flagged: zero-argument `request()`, static calls on `Illuminate\Http\Request` itself (`Request::capture()`), ArrayAccess (`$request['x']`), magic properties (`$request->x`) and `InputBag` reads (`$request->query->get('x')`, `->headers`, `->cookies`). Watch the `InputBag` path in code review so it does not become a way around the rule.

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

`App\Providers` and `App\Http\Responses` are excluded by default: their signatures come from the framework and have no FormRequest to route data through. `UnvalidatedFormRequestFieldRule` reuses these namespaces and keeps its own reader list under `unvalidatedFormRequestField.accessors` (defaults in `extension.neon`).

<details>
<summary>Adopting on an existing codebase</summary>

The first run reports a lot. Generate a baseline and work it down over several PRs:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

Patterns that stay baselined:

- Dynamic-key admin CRUD, such as `$request->collect('fields')->each(...)` with schema-driven keys. Suppress inline:
  ```php
  // @phpstan-ignore hihaho.validation.noUnsafeRequestData
  $value = $request->input($field->key);
  ```
- `JsonResource::toArray(Request)`. Add `App\Http\Resources` to `excludeNamespaces` if you accept the pattern.

Common traps:

- An injected FormRequest is not enough. The rule fires when it has no `rules()` (an auth-only wrapper) or its rules do not cover the key.
- `validated()` drops keys outside `rules()`, nested ones included. A read of `interactions.$.foo` only migrates once a nested rule covers it.
- Livewire and Filament input arrives through component props and form schemas, which these rules do not read. A low hit count there proves nothing; review `mount()` and submit paths yourself.
- A hit in a `Support` or utility namespace often points at dead code. Check the callers before baselining it.

</details>

### Positional flag arguments

Flag a bare `true`/`false`/`null` passed **positionally** as the last argument of a **first-party** call. `setActive('name', false)` hides what the flag means; `setActive('name', active: false)` does not.

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

rector-rules' `FirstPartyFlagArgumentToNamedRector` auto-fixes the flags bare PHPStan can resolve. This rule uses your PHPStan extensions, so in a larastan-equipped app it also reports the receivers Rector cannot resolve.

- Only the last argument, and only when every argument is positional. The parameter must be named and non-variadic, but need not be bool-typed: a bare `null` on a `?Object` parameter is just as opaque.
- Only first-party callees, judged by the method's **declaring** class: vendor parameter names are not semver-stable, so an `App\` class inheriting a vendor method is not flagged.
- Only calls that reach a **native** method. `@method` and `@mixin` methods, macros, larastan's forwarded builder and model-static calls, and anything else that runs through `__call` / `__callStatic` receive their arguments positionally, so a named flag would arrive under a string key. Also skipped: `@no-named-arguments` callees, `static::` calls and `new static` (a subclass may rename the parameter), and a union receiver where a member lacks the method.

```neon
parameters:
    positionalFlagArgument:
        firstPartyNamespaces:
            - App
            - Database\Factories
            - Tests
```

#### Named-argument manifest (opt-in)

rector-rules' `NamedArgumentFromManifestRector` names flags at the call sites only larastan can resolve. It reads a JSON manifest that this package writes when you include the opt-in extension:

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

`vendor/bin/phpstan analyse` then writes one record per site (`{file, line, method, argIndex, paramName, value}`) and reports no errors. Your baseline does not apply: baselined sites still appear. A nested `outputPath` gets its parent directory created.

### No Eloquent `$with` property

Flag a non-empty `$with` property on an Eloquent `Model`. It eager-loads its relations on **every** query for that model, whether the caller needs them or not. Load relations at the call site instead.

| Rule                          | Targets                                   | Identifier                                 |
|-------------------------------|-------------------------------------------|--------------------------------------------|
| `NoEloquentWithPropertyRule`  | `protected $with = [...]` on a `Model`    | `hihaho.conventions.noEloquentWithProperty`|

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Interaction extends Model
{
    protected $with = ['chapters'];  // reported — eager-loads on every query
    protected $with = [];            // fine — explicit empty default is a no-op
}

// Load where actually needed:
Interaction::query()->with('chapters')->get();
$interaction->loadMissing('chapters');
```

### Trait requires interface

Flag a class or enum that uses a configured trait without implementing the interface that trait belongs with. PHP cannot make a trait require an interface, so nothing breaks at runtime, but every tool that finds such classes **by interface** skips it.

| Rule                          | Targets                                              | Identifier                                 |
|-------------------------------|------------------------------------------------------|--------------------------------------------|
| `TraitRequiresInterfaceRule`  | class/enum using trait `T` without implementing `I`  | `hihaho.conventions.traitRequiresInterface`|

It checks nothing by default. Each project maps `trait => interface`:

```neon
parameters:
    traitRequiresInterface:
        App\Enums\Concerns\HasLocalization: App\Enums\Contracts\LocalizedEnumContract
        App\Enums\Concerns\SideMenu: App\Enums\Contracts\SideMenuContract
```

```php
enum PortalPdfState: string          // reported — has localizationKey(), invisible to the contract
{
    use HasLocalization;
}

enum ActionType: int implements BaseActionType   // fine — BaseActionType extends the contract
{
    use HasLocalization;
}
```

Interfaces and traits reached through a parent class, another interface or another trait count. Traits and interfaces themselves are never flagged. A configured name that does not exist, or is the wrong kind, aborts the analysis with an `InvalidArgumentException`, so a typo cannot silently disable the rule.

### Unlocked Livewire id properties

Flag a public id property on a Livewire component or form object that is not `#[Locked]`. The browser can set any unlocked public property, so a user who edits `postId` in the request payload points the next action at a record they cannot see.

| Rule                     | Targets                                                    | Identifier                           |
|--------------------------|------------------------------------------------------------|--------------------------------------|
| `UnlockedIdPropertyRule` | public `id`, `*Id`, `*Ids`, `*Uuid`, `*_id` … property without `#[Locked]` | `hihaho.livewire.unlockedIdProperty` |

It checks nothing by default. Each project lists the namespaces to check:

```neon
parameters:
    unlockedLivewireIdProperty:
        namespaces:
            - App\Livewire
        excludeNamespaces: []
```

```php
final class EditPost extends Component
{
    public int $postId;             // reported

    #[Locked]
    public int $categoryId;         // fine

    public bool $showId = false;    // fine — a bool cannot point at a record
}
```

Only the class's own public, non-static properties are read, not ones a trait adds. An id the user picks on purpose (a select, a checkbox list) is fine when the server validates or scopes it; mark it with an ignore that says how:

```php
public ?int $authorId = null; // @phpstan-ignore hihaho.livewire.unlockedIdProperty (validated with exists, scoped to the user's team)
```

### Inheritable setting reads

Flag a direct read of a setting that stores `null` to mean "inherit from the parent". The read treats that `null` as the value, so the child ignores what it should inherit. Use the model's resolved getter instead.

| Rule                         | Targets                                         | Identifier                                  |
|------------------------------|-------------------------------------------------|---------------------------------------------|
| `InheritableSettingReadRule` | `$model->setting` read on a configured class    | `hihaho.conventions.inheritableSettingRead` |

It checks nothing by default. Each project lists its classes and their inheritable properties:

```neon
parameters:
    inheritableSettings:
        App\Models\Page:
            - layout
            - comments_enabled
```

```php
$page->layout;                  // reported
$page?->layout;                 // reported
isset($page->layout);           // reported — still reads the raw column
$page->resolvedLayout();        // fine
$page->layout = null;           // fine — writing null is how a setting goes back to inheriting
$section->layout;               // fine — not a configured class
```

A read through a subclass counts. The getters that resolve the inheritance must read the raw column, so exempt them by path or with an ignore that names the reason:

```neon
parameters:
    ignoreErrors:
        -
            identifier: hihaho.conventions.inheritableSettingRead
            paths:
                - app/Models/Concerns/InheritsPageSettings.php
```

A configured class that does not exist aborts the analysis. Blade templates and query-builder column strings are not analysed.

### Slow migration DDL on outlier tables

Flag migration DDL that MySQL cannot apply instantly to a very large table. An `ADD INDEX` or `ADD FOREIGN KEY` there rebuilds the table under an exclusive metadata lock, and every write queues behind it for the duration of the deploy.

| Rule                     | Targets                                                   | Identifier                                            |
|--------------------------|-----------------------------------------------------------|-------------------------------------------------------|
| `SlowMigrationDdlRule`   | `->index()`/`->foreign()`/`->after()`/… on an outlier      | `hihaho.database.slowMigrationDdl`                    |
|                          | column add or `dropColumn()` without `->instant()`         | `hihaho.database.migrationColumnWithoutInstant`       |
|                          | `timestamps()`/`softDeletes()`/`morphs()` on an outlier     | `hihaho.database.slowMigrationDdl`                    |
|                          | raw `ALTER TABLE` without `ALGORITHM=INSTANT`              | `hihaho.database.rawAlterWithoutInstant`              |
|                          | raw `ALTER TABLE` whose target cannot be read statically   | `hihaho.database.unresolvableAlterTarget`             |
|                          | `Schema::table()` whose definition cannot be read          | `hihaho.database.uncheckableSchemaChange`             |
|                          | `Schema::rename()`/`drop()`/`dropIfExists()` on an outlier | `hihaho.database.outlierTableDestructiveSchemaCall`   |

It checks nothing by default. Each project measures its own row counts and lists the tables:

```neon
parameters:
    outlierTables:
        - video_sessions
        - video_session_questions
        - video_session_answers
```

Migrations are usually outside a project's analysed `paths`; the rule reports nothing until `database/migrations` is added.

```php
return new class extends Migration
{
    private string $table = 'video_sessions';

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table): void {
            $table->index('external_learner_id');          // reported — rebuilds the table
            $table->string('external_learner_id', 255);    // reported — no ->instant()
            $table->string('locale', 8)->instant();        // fine — MySQL rejects what it cannot apply instantly
        });

        DB::statement("ALTER TABLE `{$this->table}` ADD COLUMN `foo` BIGINT NULL");                    // reported
        DB::statement("ALTER TABLE `{$this->table}` ADD COLUMN `bar` BIGINT NULL, ALGORITHM=INSTANT"); // fine
    }
};
```

- `->instant()` compiles to `algorithm=instant`, so MySQL rejects an operation it cannot apply instantly instead of rebuilding the table. Move index and foreign-key work to a queued job outside the deploy.
- `timestamps()`, `softDeletes()` and the `morphs()` family return `void`, so they cannot carry `->instant()`. Add those columns one by one instead.
- The whole class is read, `down()` included. Only creation calls are flagged: `dropIndex()`/`dropForeign()` in `down()` are fine, and so is a foreign key from a new, empty table to an outlier.
- A raw statement is keyed on the table it ALTERs, so `ALTER TABLE lti_grades ... REFERENCES video_sessions` is fine. One whose target cannot be read statically reports `unresolvableAlterTarget`: name the table with a literal or assert `ALGORITHM=INSTANT`.

<details>
<summary>Helpers and loops</summary>

The table and the definition are traced through a private helper, so a resumable migration that guards each statement is still checked:

```php
$this->addColumn('video_sessions', 'locale', fn (Blueprint $table) => $table->string('locale', 8));

private function addColumn(string $table, string $column, Closure $definition): void
{
    if (Schema::hasColumn($table, $column)) {
        return;
    }

    Schema::table($table, $definition);   // reported at the call site above
}
```

Each call site pairs the table it passes with the definition it passes, so a helper used for an outlier and for an ordinary table reports only the first.

Definitions in an array property the migration loops over are read too, when one literal fills the property and nothing changes it afterwards. Reading it (`count($this->columns)`, `array_keys()` in `down()`) is fine.

Everything else reports `uncheckableSchemaChange`: a definition built elsewhere, an array written twice or aliased by reference, an argument passed by reference, a helper nothing calls. Write the closure at the call site to clear it.

</details>

### Swallowed exceptions

Flag a `catch (Throwable)` or `catch (Exception)` that neither rethrows, reports, nor reads what it caught. Such a catch turns every failure in the `try` block into silent success: the job completes, the request returns 200, and nothing reaches the error tracker.

| Rule                     | Targets                                         | Identifier                             |
|--------------------------|-------------------------------------------------|----------------------------------------|
| `SwallowedExceptionRule` | broad catch whose body never handles the throw  | `hihaho.exceptions.swallowedException` |

It checks nothing by default. Each project lists the namespaces to check:

```neon
parameters:
    swallowedException:
        namespaces:
            - App
        excludeNamespaces:
            - App\Dev
        reportNarrowCatches: false   # true also checks catch (SomeSpecificException)
```

A catch counts as handled when its body contains any of:

- a `throw`
- `report()`, `report_if()`, `report_unless()`, `logger()`, `abort*()`, or any `Log::` call
- `->fail()` or `->release()` on a job. `->failed()` does not count: calling the hook yourself still finishes the job as a success.
- any read of the caught variable, such as `$this->notify($e)`, `return $e` or `fn () => $e->getMessage()`
- `return Response::deny(...)` (`Illuminate\Auth\Access\Response`) or a call to a validation rule's `$fail` closure

```php
try {
    $this->sync();
} catch (Throwable) {          // reported
    return 'default';
}

try {
    $this->sync();
} catch (Throwable $e) {       // fine — $e reaches a helper
    $this->notifyFailure($e);
}
```

Exempt, because the return value carries the failure:

- a method returning `bool` named `check*`, `can*`, `is*`, `has*`, `supports*`, `verify*`, `declares*`, `validate*` or `should*`
- a method returning a nullable type named `try*`, `parse*`, `maybe*`, `find*`, `fetch*` or `resolve*`
- a catch nested inside a catch that already handles its exception (not the other way round)

A comment in the body is not an exemption. Mark a deliberate swallow with an ignore that says why:

```php
} catch (Throwable) { // @phpstan-ignore hihaho.exceptions.swallowedException (best-effort cache warmup)
```

To make that reason mandatory, set PHPStan's `reportIgnoresWithoutComments: true` in your `phpstan.neon`. The package does not set it for you, because it applies to every ignore in the project.

### Unescaped values in raw-HTML sinks

Flag a value that can carry markup when it reaches code that prints HTML without escaping it. spatie/laravel-html escapes attribute values and `->text()`, and nothing else. `html()->a($href, $contents)`, `->span($contents)`, `->div($contents)`, `->html($html)` and `->children($children)` all render their content raw. A Nova field marked `->asHtml()` prints its value raw too.

| Rule                                    | Targets                                                         | Identifier                           |
|-----------------------------------------|-----------------------------------------------------------------|--------------------------------------|
| `CombinedMethodCallRule` / `CombinedStaticCallRule` | content argument of a spatie builder, element or `Html::` facade call | `hihaho.html.unescapedBuilderContent` |
| `UnescapedHtmlFieldClosureRule` / `UnescapedHtmlFieldArrowFunctionRule` | return value of a `make()`, `displayUsing()` or `resolveUsing()` callback on an `->asHtml()` Nova field | `hihaho.html.unescapedHtmlField` |

It checks nothing by default. Each project lists the namespaces to check:

```neon
parameters:
    unescapedHtml:
        namespaces:
            - App
        excludeNamespaces: []
```

Concatenations, interpolations, ternaries, `??` and `sprintf()` are split, and each part is judged on its own. A part is safe when it is:

- a literal string, an int, a float, a bool, null or a backed enum
- passed through `e()`, `htmlspecialchars()` or `htmlentities()`
- an `Htmlable`, such as a builder chain ending in `->text()`, or that object's `->toHtml()`

Anything else is reported, including a plain `string` variable.

```php
Text::make('Title')->displayUsing(fn (?string $title) => html()->a($url, $title)->toHtml())->asHtml();    // reported — a() renders $title raw
Text::make('Title')->displayUsing(fn (?string $title) => html()->a($url)->text($title)->toHtml())->asHtml(); // fine

Text::make('Name')->displayUsing(fn (string $name) => "<b>{$name}</b>")->asHtml();          // reported
Text::make('Name')->displayUsing(fn (string $name) => '<b>' . e($name) . '</b>')->asHtml(); // fine
```

Each `return` in a closure is judged on its own. Not checked: a `children()` call with a mapper (the mapper's own builder calls are), and a bare `->asHtml()` field with no callback.

Mark trusted markup with an ignore that says why. See [Swallowed exceptions](#swallowed-exceptions) for making the reason mandatory:

```php
$html->div($sanitised); // @phpstan-ignore hihaho.html.unescapedBuilderContent (purified on save)
```

## Disallowed-call presets

Opt-in configuration for [spaze/phpstan-disallowed-calls](https://github.com/spaze/phpstan-disallowed-calls). It is not loaded with the extension. Install spaze, then include the presets you want:

```neon
includes:
    - vendor/hihaho/phpstan-rules/presets/disallowed-str-limit.neon
    - vendor/hihaho/phpstan-rules/presets/disallowed-file-responses.neon
```

| Preset                           | Bans                                                                 | Identifier                       |
|----------------------------------|----------------------------------------------------------------------|----------------------------------|
| `disallowed-str-limit.neon`      | `Str::limit()`, `Stringable::limit()`                                | `hihaho.disallowed.strLimit`     |
| `disallowed-file-responses.neon` | download and stream responses, in the paths listed in `fileResponsePaths` | `hihaho.disallowed.fileResponse` |

**`Str::limit()`** cuts by display width (`mb_strwidth`), not by character count, so it does not enforce a column length. Use `mb_substr()` for a cap. For display text, cutting by width is correct, so exempt those paths by identifier:

```neon
parameters:
    ignoreErrors:
        -
            identifier: hihaho.disallowed.strLimit
            paths:
                - app/Notifications/*
```

**File responses.** A serverless runtime caps the response body: behind Vapor, Lambda base64-encodes binary bodies, which leaves about 4.4 MB for the raw file. The preset bans these in the configured paths:

- `response()->download()`, `->streamDownload()` and `->file()`
- `FilesystemAdapter::download()` and `->response()`, and their `Storage::` facade forms
- `Excel::download()` and the `Exportable::download()` trait method (maatwebsite/excel)
- `new StreamedResponse` and `new BinaryFileResponse`

Store the file and redirect to a temporary signed URL instead. `fileResponsePaths` has no default, so including the preset without it fails the configuration. An empty list bans the calls everywhere.

```neon
parameters:
    fileResponsePaths:
        - app/Http/*
        - app/Livewire/*
```

A file known to stay small takes an ignore with a reason:

```php
return response()->download($path); // @phpstan-ignore hihaho.disallowed.fileResponse (static asset under 50 KB)
```

## Type extensions

### Stubbed methods

`StubbedMethodsClassReflectionExtension` declares **instance** methods that exist at runtime but not in reflection, such as Faker providers added through `__call` and Laravel macros. They resolve to the configured return type, and a misspelled name still fails. Static calls (`__callStatic`) are not covered.

It resolves nothing by default. Map `class => (method => return type)`:

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
        Laravel\Nova\Fields\Number:
            onlyOnExport: '$this'
```

Any PHPDoc type works. `$this`, `static` and `self` bind to the receiver, so a fluent macro keeps its chain typed. Arguments are not checked.

### Collection `values()->all()` list typing

`CollectionListAllReturnTypeExtension` types `->values()->all()` on a `Collection`/`LazyCollection` as `list<TValue>` instead of `array<int, TValue>`, so a `@return list<T>` method needs no `array_values()` wrapper. It is registered automatically.

```php
/** @return list<int> */
public function ids(Collection $users): array
{
    return $users->map(fn (User $user): int => $user->id)->values()->all(); // list<int>, no array_values()
}
```

The receiver must be a direct `->values()` call on a `Support\Collection`/`LazyCollection` or subclass. A chain split across variables, or a bare `Enumerable`, keeps the default type.

### Route-model binding typing

`RouteBindingReturnTypeExtension` types `$this->route('x')` / `$request->route('x')` as the model bound to parameter `x`, so you no longer need an `assert($model instanceof Video)` after each call. It reads `Route::model('x', M::class)` and `Route::bind('x', fn (): M => …)` from the providers you list:

```neon
parameters:
    routeBindingProviders:
        - App\Providers\RouteServiceProvider
```

```php
public function handle(Request $request): void
{
    $video = $request->route('video_id'); // Video — no assert() needed
}
```

> **The bound model is typed non-null.** That is wrong when the current route lacks the parameter (an optional `{param?}` segment, or middleware shared with routes without it). Keep an explicit null check in that code.

> **Expect a one-time cleanup.** Existing `assert()` and `instanceof` guards after `route('x')` become "always true", and `treatPhpDocTypesAsCertain: false` does not soften that. Remove them, except on the routes above.

Only a single constant-string argument is narrowed, and only the instance method (not the `Request::` facade). A `Route::bind()` closure without a return type, and a `Route::model()` with a missing-model callback, are skipped.

**Implicit bindings.** List your route files to also type parameters bound by the controller's type-hint:

```neon
parameters:
    routeFiles:
        - routes/web.php
        - routes/api.php
```

It reads `Route::<verb>('uri/{param}', Action)` for an invokable `Controller::class` or `[Controller::class, 'method']`, matches the parameter by name or `Str::snake()` as Laravel does, and unions across routes. Explicit provider bindings win. Closures, group-level controllers, `Route::resource`, non-model type-hints and unreadable files keep the default type. Paths resolve from the directory PHPStan runs in, and the files are parsed, not executed.

### Relation-existence closure builder typing

`RelationExistenceClosureBuilderParameterExtension` types the closure passed to `whereHas`, `orWhereHas`, `whereDoesntHave`, `orWhereDoesntHave`, `has`, `orHas`, `doesntHave` and `orDoesntHave` as the *related* model's builder. Without it, a string relation name falls back to `Builder<Model>`, and under `checkModelProperties` every valid related column fails. It is registered automatically.

```php
/** @param Builder<User> $query */
public function scopeWithPublishedPosts(Builder $query): void
{
    $query->whereHas('posts', function (Builder $q): void {
        $q->where(Post::STATUS, Post::PUBLISHED); // $q is Builder<Post>
    });
}
```

Nested relations (`'posts.comments'`) resolve to the last model, and a custom builder is kept. A relation it cannot prove keeps the default type.

> **Use `void` closures.** `Builder<TModel>` is invariant, so a closure that *returns* the narrowed builder (`fn ($q) => $q->where(...)`) reports `return.type: should return Builder<Model> but returns Builder<RelatedModel>`. The return value is ignored at runtime anyway.

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
