# No Unsafe Request Data

## Overview

PHPStan rule that forbids reading unvalidated input from `Illuminate\Http\Request` (including `FormRequest` subclasses) in application code. Controllers and actions must consume data via the return values of `$request->validated()`, `$request->safe()`, or the array returned from `$request->validate([...])` — never via raw readers like `input()`, `all()`, `get()` on the request object itself. Note: a `FormRequest` typehint triggers automatic validation, but raw readers inherited from `Request` still return the full payload (including unvalidated keys), so they must be flagged on `FormRequest` too. Replaces the abandoned PR #29, which matched variable names and reflected user classes; this rewrite uses only PHPStan's type system.

---

## 1. Current State

- No such rule exists. PR #29 (`ScopeRequestValidateMethods`) proposed a version but is closed/abandoned due to severe design flaws:
  - Matches variable names literally (`$request`, `$safe`, `$validated`) instead of types — renaming silently bypasses the rule.
  - Uses `new ReflectionClass($fqn)` on user code instead of PHPStan's `ReflectionProvider`.
  - Manually constructs `PHPStan\Node\AnonymousClassNode` (BC-unsafe; author acknowledged in commit message).
  - Hardcodes `App\Http\Request` prefix via `str_starts_with` — also matches `App\Http\RequestHandlers`.
  - 9-branch `nodeName()` helper confuses Laravel `Stringable` with php-parser nodes.
  - Config `allowedRequestMethods` inversion flags valid Laravel methods like `$request->string()`.
  - `nonIgnorable()` on a heuristic rule.
- Existing package conventions (`src/Rules/Debug/NoDebugInNamespaceRule.php:1`, `src/Traits/ChecksNamespace.php:1`) show the expected pattern: `final readonly` rule class, `ChecksNamespace` trait for namespace gating, fixtures under `tests/Rules/{Group}/stubs/`.

## 2. Proposed Design

### 2.1 Rule class

- Location: `src/Rules/Validation/NoUnsafeRequestDataRule.php`.
- `final readonly class NoUnsafeRequestDataRule implements Rule` with docblock `@implements Rule<MethodCall>` (PHPStan generics are docblock-only — mirror `src/Rules/NoInvadeInAppCode.php:13`).
- Uses `ChecksNamespace` trait to gate on `App\` (configurable via constructor for consumers that use a different root namespace).
- Constructor accepts:
  - `list<string> $unsafeMethods` — default: `input`, `all`, `get`, `query`, `post`, `only`, `except`, `collect`, `string`, `str`, `integer`, `boolean`, `float`, `json`, `keys`, `fluent`, `array`, `date`, `enum`, `enums`. (Predicate-style `has`/`filled` excluded — return `bool`, not data.)
  - `list<string> $namespaces` — default: `['App']`. Rule fires only when scope namespace starts with one of these.

### 2.2 Core logic

Targets `PhpParser\Node\Expr\MethodCall`. For each call:

1. **Namespace gate.** If no configured namespace prefix matches `$scope->getNamespace()` → return `[]`. Implemented by iterating the `$namespaces` list and short-circuiting on `namespaceStartsWith()`.
2. **Exclude request-class scopes.** If scope class is-a `Illuminate\Http\Request` (covers `FormRequest` and subclasses) → return `[]`. Inside a request class, raw reads are legitimate — that is where validation pulls its source data. This exemption covers both `$this->input()` and `$someRequest->input()` forms inside request-class methods.
3. **Resolve receiver type.** `$receiverType = $scope->getType($node->var)`.
4. **Must be a Request.** If `(new ObjectType(Request::class))->isSuperTypeOf($receiverType)` is not `yes()` → return `[]`. Skips unrelated types and `ValidatedInput` (returned by `safe()`), so `$request->safe()->input()` passes. **Union-type limitation:** `Request|Other` returns `maybe()` and is skipped — documented as known false-negative in §3. Flag only on `yes()` for the MVP to keep false-positive rate at zero.
5. **Resolve method name.** If `$node->name` is not `Identifier` → return `[]` (dynamic call; cannot statically verify).
6. **Flag if unsafe.** If `$node->name->toString()` is in `$unsafeMethods` → build error. Applies equally to `Request` and `FormRequest` receivers — the `FormRequest` typehint validates *on dispatch* but `$formRequest->all()` still returns the raw payload (including keys outside `rules()`).

Type checks use PHPStan's type API exclusively — no `new ReflectionClass`, no `instanceof` on PHPStan types. Scope-class check in step 2 uses `$scope->getClassReflection()` + `ObjectType::isSuperTypeOf(new ObjectType($classReflection->getName()))`.

### 2.3 `request()` helper

Phase 1 already covers **chained** calls like `request()->input('key')` — the `MethodCall` receiver type resolves to `Illuminate\Http\Request` whether the var is a `Variable` or a `FuncCall`. Only the **direct-arg form** `request('key')` escapes Phase 1 (it is a `FuncCall`, not a `MethodCall`). Phase 2 adds a second rule targeting `FuncCall` to cover it. Confirm the chain case in a Phase 1 test before writing Phase 2 to avoid dead code.

### 2.4 Error

Message is built with `sprintf`, interpolating the resolved method name so the error points at the specific call site:

```php
RuleErrorBuilder::message(sprintf(
    'Reading unvalidated request data via %s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
    $methodName,
))
    ->identifier('hihaho.validation.noUnsafeRequestData')
    ->tip('Inject a FormRequest subclass, or call $request->validated() / $request->safe() before reading input.')
    ->build();
```

Error is ignorable (no `nonIgnorable()`) — legitimate escape hatches exist (e.g. middleware handling pre-validation data).

### 2.5 Registration

Use `services:` block in `extension.neon` (constructor args). Parameters schema allows consumer override of both `unsafeMethods` and `namespaces`.

```neon
parametersSchema:
    noUnsafeRequestData: structure([
        unsafeMethods: listOf(string()),
        namespaces: listOf(string()),
    ])

parameters:
    noUnsafeRequestData:
        unsafeMethods:
            - input
            - all
            - get
            - query
            - post
            - only
            - except
            - collect
            - string
            - str
            - integer
            - boolean
            - float
            - json
            - keys
            - fluent
            - array
            - date
            - enum
            - enums
        namespaces:
            - App

services:
    -
        class: Hihaho\PhpstanRules\Rules\Validation\NoUnsafeRequestDataRule
        arguments:
            unsafeMethods: %noUnsafeRequestData.unsafeMethods%
            namespaces: %noUnsafeRequestData.namespaces%
        tags:
            - phpstan.rules.rule
```

### 2.6 Tests

- `tests/Rules/Validation/NoUnsafeRequestDataRuleTest.php` extends `RuleTestCase<NoUnsafeRequestDataRule>`.
- Fixtures under `tests/Rules/Validation/stubs/`.
- Uses `$this->analyse([...], [[message, line], ...])` pattern (not `gatherAnalyserErrors` + filter).
- Fixtures cover:
  - Controller with `Illuminate\Http\Request` param calling unsafe methods → flagged.
  - Controller with `FormRequest` subclass param calling `input()`/`all()`/`get()` → **flagged** (raw readers remain unsafe even after auto-validation — they return the full payload, not `rules()`-filtered data).
  - Controller calling `$request->validated()` / `$request->safe()->input()` → not flagged.
  - Controller calling `$request->validate([...])` (the call itself) → not flagged. A *subsequent* `$request->input()` on the same variable is still flagged — the rule cannot track "validated earlier"; use the array returned by `validate()`.
  - Code outside `App\` namespace (e.g. vendor-style `Illuminate\`) → not flagged.
  - `FormRequest` class calling `$this->input()` internally → not flagged (scope-class exemption, step 2).
  - Dynamic method call `$request->{$name}()` → not flagged.
  - Receiver typed `Request|Foo` (union) → not flagged in MVP (documented limitation).
  - `request()->input('x')` chain → flagged by this rule (confirms Phase 2 scope).

## 3. Out of Scope

- ArrayAccess reads: `$request['key']` — uses `offsetGet`, not a named method; would need a separate `ArrayDimFetch` rule.
- Magic property reads: `$request->key` — `__get` proxies to `input()`; would need a separate `PropertyFetch` rule.
- Union-type receivers (`Request|Other`) — MVP flags only when `isSuperTypeOf(...)->yes()`. Revisit if false negatives show up in practice.
- Tracking flow of local variables — a `$data = $request->input('x')` followed by use of `$data` is not flagged at the `$data` site; only the `input()` call is.
- Autofix via `fixNode()` — no single-node replacement exists; structural fix belongs in rector-rules sister package.

## Implementation

### Phase 1: Core rule (Priority: HIGH)

- [x] Create `src/Rules/Validation/NoUnsafeRequestDataRule.php` — `final readonly class ... implements Rule` with `@implements Rule<MethodCall>` docblock, uses `ChecksNamespace`.
- [x] Constructor uses promoted properties: `list<string> $unsafeMethods`, `list<string> $namespaces`.
- [x] Decorate `getNodeType()` and `processNode()` with `#[Override]` to match sibling rules (`src/Rules/Debug/NoDebugInNamespaceRule.php:20`).
- [x] `getNodeType(): string { return MethodCall::class; }`.
- [x] `processNode()` implements 6-step logic in §2.2. Type checks via `ObjectType::isSuperTypeOf()->yes()` only — no reflection, no `instanceof` on PHPStan types. No separate FormRequest branch — raw readers are unsafe on any `Request` subtype.
- [x] Namespace gate iterates `$this->namespaces` and short-circuits on first `namespaceStartsWith($scope, $ns)` match.
- [x] Error built per §2.4 using `sprintf` with resolved method name.
- [x] Register in `extension.neon` under `services:` with `parametersSchema` + `parameters` blocks.
- [x] Tests — fixtures and test class per §2.6. Cover each fixture case as a separate `#[Test]` method. Include an identifier assertion test mirroring `NoDebugInNamespaceTest::should_have_correct_error_identifier_in_app()`. Include explicit test that `request()->input('x')` (chained) is flagged — confirms Phase 2 only needs the direct-arg form. Include a `FormRequest`-in-controller fixture that **must flag** `$userRequest->all()` / `$userRequest->input('x')` (regression test against the old design).

### Phase 2: `request()` helper direct-arg form (Priority: MEDIUM)

- [x] Add `src/Rules/Validation/NoUnsafeRequestHelperRule.php` targeting `FuncCall`.
  - Fires only when `$node->name` resolves to the global `request` function **and** the call has at least one argument.
  - Skip zero-arg `request()` — returning the Request is fine; subsequent method calls are caught by Phase 1.
- [x] Share the configured `$namespaces` list across both rules (inject via neon `arguments`). No need to share `$unsafeMethods`.
- [x] Tests — fixtures covering `request('foo')` (flagged), `request()` (not flagged), `request()->all()` (flagged by Phase 1 regression check).

### Phase 3: Documentation (Priority: LOW)

- [x] Add README section describing the rule, config parameters, and escape hatches.
- [x] Update `UPGRADING.md` if rule ships in a new major version.

---

## Open Questions

1. **Custom namespace roots.** Package default is `App`. Should the rule ship a `namespaces: []` empty default (no-op until configured) to avoid surprising consumers who use a different root, or keep `App` as an opinionated default?
2. **Nova request.** `Laravel\Nova\Http\Requests\NovaRequest` extends `FormRequest` — step 2 scope-class exemption covers it inside NovaRequest methods. Verify with a fixture that `$novaRequest->all()` inside a Nova action *is* flagged (same policy as FormRequest).

---

## Resolved Questions

1. **Should `$this->input()` inside an `App\Http\Requests\` FormRequest be flagged?** **Decision:** No — scope-class exemption (§2.2 step 2). **Rationale:** Raw reads inside a request class are how `rules()`/custom accessors work; the validation source is `$this->all()` inside the framework itself.
2. **Should raw readers on a `FormRequest` typehint in a controller be flagged?** **Decision:** Yes. **Rationale:** Codex review identified that `FormRequest::input()`/`all()` inherit from `Request` and still return the full raw payload including keys outside `rules()`. Auto-validation at dispatch does not sanitize the request object itself.
3. **Include `has`/`filled` in default unsafeMethods?** **Decision:** No. **Rationale:** They return `bool`, not data. Common control-flow patterns (`if ($request->has('x'))`) would flag with high false-positive rate.
4. **Is `str()` a macro or core?** **Decision:** Core — include in defaults. **Rationale:** Defined on `Illuminate\Support\Traits\InteractsWithData::str()` used by `Request`.
5. **Union-type receivers.** **Decision:** Flag when *any* union member is-a `Request`. **Rationale:** Codex adversarial review identified the original MVP `yes()`-only check as a silent bypass (`Request|Other` → `maybe()` → skipped). Implemented via `Type::getObjectClassNames()` iteration in `NoUnsafeRequestDataRule::typeIsRequest()`. `mixed`/`object`/non-object types still skip (no class names returned).
6. **Case-insensitive method matching.** **Decision:** Normalize method names to lowercase before comparing against `unsafeMethods`. **Rationale:** Codex flagged that PHP method names are case-insensitive, so `$request->INPUT()` would bypass a case-sensitive match. Constructor pre-lowercases the configured list; `processNode` lowercases the resolved name before `in_array`.
7. **Case-insensitive + aliased `request()` helper.** **Decision:** Use PHPStan's `ReflectionProvider::hasFunction()` + `getFunction()->getName()` (lowercased) instead of raw `Name::toString()`. **Rationale:** Codex flagged that raw-text matching misses aliased imports (`use function request as foo`) and flags same-named namespace-local functions. The reflection-provider path resolves the call to its canonical function name including alias resolution.

## Findings

- **Phase 1 complete.** 9/9 tests pass. Rule at `src/Rules/Validation/NoUnsafeRequestDataRule.php`, registered in `extension.neon`.
- **Classmap registration required for stubs.** Added `tests/Rules/Validation/stubs/` to `composer.json` `autoload-dev.classmap`. Without it PHPStan's `ReflectionProvider` couldn't resolve the `SharedUserFormRequest → FormRequest → Request` hierarchy inside stubs, and the type check at step 4 returned `no()` — silently skipping the regression test. Existing Debug stubs use the same classmap pattern (sibling entries).
- **Phase 1 catches `request()->input()` chains.** Confirmed by `flags_chained_request_helper` test — stock PHPStan resolves the conditional return type of the Laravel `request()` helper. Phase 2 only needs `request('key')` (direct-arg `FuncCall`), matching spec §2.3.
- **Union receiver test confirmed MVP limitation.** `Request|UnionReceiverOther` does not flag because `isSuperTypeOf(...)` returns `maybe()`, and the rule only fires on `yes()`.
- **Intelephense P1009/P1013 warnings** in editor for PHPStan classes are false positives — types live in `phpstan.phar` which the IDE indexer doesn't traverse. Runtime resolution and PHPStan analysis both fine.
- **Phase 2 complete.** 4/4 tests pass. `NoUnsafeRequestHelperRule` at `src/Rules/Validation/NoUnsafeRequestHelperRule.php` targeting `FuncCall` for `request('key')` direct-arg form. Shared `namespaces` parameter via same `%noUnsafeRequestData.namespaces%` expansion. Identifier: `hihaho.validation.noUnsafeRequestHelper`.
- **Post-implementation hardening (Codex adversarial review).** Three findings applied:
  1. Method-name match lowercased both sides (prevents `$request->INPUT()` bypass).
  2. Helper-function match switched to `ReflectionProvider::getFunction()->getName()` lowercased (handles `\request`, `use function request as foo`, and rejects same-named namespaced functions).
  3. Union-receiver check iterates `Type::getObjectClassNames()` — `Request|Other` now flags. MVP limitation removed; only `mixed`/non-object receivers still skip.
- **Cognitive complexity hit 13 after refactor.** Package cap is 12. Reduced by extracting `classIsRequest(string)` helper shared between `scopeClassIsRequest` and `typeIsRequest`, and reducing `processNode` branching by short-circuiting on cheap checks (identifier + method name) before expensive scope/type queries. Final PHPStan clean.
- **Final state:** 59 tests, 81 assertions, all green. PHPStan level max clean. Pint clean.
- **Phase 3 complete.** `README.md` gets per-rule sections for `NoUnsafeRequestDataRule` and `NoUnsafeRequestHelperRule` matching the style of existing rule entries (description, code example, identifier, config block, out-of-scope notes). `UPGRADING.md` gets a provisional "3.x → 4.0" section at the top covering: what flags, migration example (raw reads → `validated()`), policy overrides, per-call-site suppression via identifier, and the known-limitation list. Version heading is a placeholder — adjust when the actual release tag is cut.
- **Peer review (mijntp) found two fixable gaps; both applied:**
  1. Custom base `App\Http\Requests\FormRequest extends LaravelFormRequest` — validated via new `CustomBaseFormRequestStub` + `CustomBaseFormRequestInControllerStub` fixtures and two tests. PHPStan's `ObjectType::isSuperTypeOf()` walks the chain; tests lock the behavior.
  2. **Static facade `Illuminate\Support\Facades\Request::input('x')` was a silent bypass** (9 hits in mijntp). Added third rule `NoUnsafeRequestFacadeRule` targeting `StaticCall` with receiver class `Illuminate\Support\Facades\Request`. Same `unsafeMethods` + `namespaces` config, case-insensitive method matching, identifier `hihaho.validation.noUnsafeRequestFacade`. 6 new tests + 5 new fixtures covering: import-form facade calls, fully-qualified facade calls, non-unsafe methods (`Request::ajax()`), outside-namespace skip, and `Illuminate\Http\Request::capture()` (different class, not flagged). README + UPGRADING updated.
- **Final state:** 67 tests, 92 assertions, all green. PHPStan level max clean on 18 files. Pint clean. Three rules shipped: `NoUnsafeRequestDataRule`, `NoUnsafeRequestHelperRule`, `NoUnsafeRequestFacadeRule`.
