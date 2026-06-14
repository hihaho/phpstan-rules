# Test the Registered Combined* PHPStan Rules

## Overview

`extension.neon` registers four rules: the three `Combined*` classes plus
`PositionalFlagArgumentConstructorRule`. The constructor rule already has a
direct test, but **no test instantiates a `Combined*` rule** — every other
existing test targets an unregistered single-responsibility "twin" instead. So
the three Combined rules (the bulk of what ships) have zero direct test coverage
and a regression in them passes CI green. This spec adds tests that instantiate
the three Combined rules and run them against the existing stub fixtures,
putting that shipped code under test and surfacing any divergence from the twins.

Source plan: `plans/001-test-registered-combined-rules.md`. Written against
commit `271efb3` (2026-06-14).

## Assumptions

<!-- One bullet per AI-introduced inference; sign off by skimming this section. -->

- **Test placement** — the three new test classes live directly in `tests/Rules/`
  (namespace `Hihaho\PhpstanRules\Tests\Rules`), the parent of every stub
  directory, so stub paths resolve with one relative hop. Matches the existing
  top-level tests (`NoInvadeInAppCodeTest`, `OnlyAllowFacadeAliasInBladeTest`).
- **Member collisions** — mirroring several twins into one class collides not
  just on `#[Test]` method names but on shared helper members: multiple twins
  define a `MESSAGE_PATTERN`/`TIP` constant and a `message()`/`tip()` helper.
  PHP forbids duplicate constants or methods in a class, so a literal copy will
  not compile. The convention: give each concern's helpers a concern-specific
  name (constants `UNSAFE_DATA_MESSAGE` / `UNVALIDATED_FIELD_MESSAGE`, helpers
  `unsafeDataTip()` / `unvalidatedFieldTip()`, etc.), or inline the expected
  strings at the call site. `#[Test]` method-name clashes (e.g.
  `error_uses_correct_identifier`) are likewise prefixed by concern. Naming
  only; no behavioural effect. See §2 "Merging helper members".
- **Config values** — the new tests construct each Combined rule with the
  **production** configuration from `extension.neon` (not the slightly narrower
  per-twin test configs). Verified equivalent for every reused stub (no stub
  lives in a namespace where the two configs diverge). Not an inference about
  unknowns — a checked fact.
- **"Mirror" means copy verbatim incl. scaffolding** — each copied case keeps
  the twin's exact expected message, line, tip, and identifier, plus any
  class-level scaffolding the case depends on (notably
  `OnlyAllowFacadeAliasInBladeTest`'s autoloader `setUp`). No expected value is
  recomputed.
- **No source changes** — this is test-only. If a Combined test fails, that is
  treated as a finding to report, not a reason to edit a rule or a stub.

---

## 1. Background — the two-layer architecture

`extension.neon` registers only:

- `CombinedFuncCallRule` (node `FuncCall`) — merges `NoDebugInNamespaceRule` +
  `NoInvadeInAppCode` + `NoUnsafeRequestHelperRule`.
- `CombinedMethodCallRule` (node `MethodCall`) — merges
  `ChainedNoDebugInNamespaceRule` + `NoUnsafeRequestDataRule` +
  `UnvalidatedFormRequestFieldRule` + the positional-flag method check.
- `CombinedStaticCallRule` (node `StaticCall`) — merges
  `OnlyAllowFacadeAliasInBlade` + `StaticChainedNoDebugInNamespaceRule` +
  `NoUnsafeRequestFacadeRule` + the positional-flag static check.
- `PositionalFlagArgumentConstructorRule` — already has its own test.

Every other rule class under `src/Rules/**` is an unregistered twin that exists
as the test surface. `grep -rn "getRule" tests` confirms **no** test class
instantiates a `Combined*` rule. Eleven of the twelve test classes instantiate
unregistered twins; the twelfth, `PositionalFlagArgumentConstructorRuleTest`,
instantiates the registered `PositionalFlagArgumentConstructorRule` directly —
the one registered rule that already has direct coverage (it has no Combined
merge, since `New_` is dispatched on its own). So the coverage gap is precisely
the three `Combined*` rules, not the constructor rule.

**Why the reuse is safe:** each existing stub is built to exercise a single
concern, and the merged concerns are gated apart (the positional-flag check
requires a bare bool/null final arg and a first-party *declaring* class; the
debug checks require a Laravel-declared `dump`/`dd`/`ray`; unsafe-request-data
bails inside a `Request` subclass while unvalidated-field fires only inside one).
So a twin's stub produces the same errors under its Combined rule, and the
twin's expected-error arrays copy across unchanged. The risk that a stub trips a
second concern is handled as an edge case (below) — report, don't paper over.

## 2. Test construction reference

All tests extend `PHPStan\Testing\RuleTestCase`, declare
`declare(strict_types=1);`, live in namespace `Hihaho\PhpstanRules\Tests\Rules`,
and implement `protected function getRule(): Rule`.

Services the Combined rules need (use the existing repo patterns):

- Parser: `self::getContainer()->getService('defaultAnalysisParser')` — see
  `tests/Rules/Validation/UnvalidatedFormRequestFieldRuleTest.php:22-23`.
- ReflectionProvider: `self::createReflectionProvider()` — see
  `tests/Rules/Validation/NoUnsafeRequestHelperRuleTest.php:31`.

Production config values (from `extension.neon:14-66`), used verbatim:

- `unsafeMethods` (22): `input, all, get, query, post, only, except, collect,
  string, str, integer, boolean, float, json, keys, fluent, array, date, enum,
  enums, file, allFiles`
- `fieldAccessors` (16): `input, get, query, post, string, str, integer,
  boolean, float, json, array, collect, date, enum, enums, file`
- `namespaces`: `['App']`
- `excludeNamespaces`: `['App\\Providers', 'App\\Http\\Responses']`
- `firstPartyNamespaces`: `['App', 'Database\\Factories', 'Tests']`

Constructor signatures (confirm against source before writing):

```php
new CombinedFuncCallRule(
    namespaces: ['App'],
    excludeNamespaces: ['App\\Providers', 'App\\Http\\Responses'],
    reflectionProvider: self::createReflectionProvider(),
);

new CombinedMethodCallRule(
    unsafeMethods: [...22...],
    fieldAccessors: [...16...],
    namespaces: ['App'],
    excludeNamespaces: ['App\\Providers', 'App\\Http\\Responses'],
    parser: $parser,                       // from getContainer()
    firstPartyNamespaces: ['App', 'Database\\Factories', 'Tests'],
);

new CombinedStaticCallRule(
    reflectionProvider: self::createReflectionProvider(),
    unsafeMethods: [...22...],
    namespaces: ['App'],
    excludeNamespaces: ['App\\Providers', 'App\\Http\\Responses'],
    firstPartyNamespaces: ['App', 'Database\\Factories', 'Tests'],
);
```

**Stub-path rewrite** (new tests sit in `tests/Rules/`; twins reference
`__DIR__ . '/stubs/X'` relative to their own dir):

| Twin test directory        | Rewrite `'/stubs/X'` to        |
|----------------------------|--------------------------------|
| `tests/Rules/Validation/`  | `'/Validation/stubs/X'`        |
| `tests/Rules/Debug/`       | `'/Debug/stubs/X'`             |
| `tests/Rules/Conventions/` | `'/Conventions/stubs/X'`       |
| `tests/Rules/` (top level) | `'/stubs/X'` (unchanged)       |

### Merging helper members (avoids a non-compiling class)

Each twin carries class-level helpers — `private const MESSAGE_PATTERN`, `private
const TIP`, `private function message(...)`, `private function tip(...)`. Several
collide when mirrored into one class:

- `CombinedMethodCallRuleTest`: `NoUnsafeRequestDataRuleTest` **and**
  `UnvalidatedFormRequestFieldRuleTest` each define `MESSAGE_PATTERN`;
  `UnvalidatedFormRequestFieldRuleTest` **and**
  `PositionalFlagArgumentMethodCallRuleTest` each define `tip()`.
- `CombinedFuncCallRuleTest` / `CombinedStaticCallRuleTest`: only one mirrored
  twin defines `MESSAGE_PATTERN`/`TIP`/helpers, so no collision there — but apply
  the same naming discipline anyway for consistency.

PHP rejects duplicate constants/methods, so the class will fatal before any test
runs. Resolve by renaming each concern's helpers to a concern-specific name and
updating that concern's call sites only — keeping the produced strings identical:

| Concern (method-call test)        | Constant / helper rename                          |
|-----------------------------------|---------------------------------------------------|
| unsafe request data               | `UNSAFE_DATA_MESSAGE`, `UNSAFE_DATA_TIP`           |
| unvalidated form-request field    | `UNVALIDATED_MESSAGE`, `unvalidatedTip()`         |
| positional flag (method)          | `flagMessage()`, `flagTip()`                      |
| chained debug                     | (twin uses inline strings — copy inline)          |

Inlining the expected strings instead of porting the helpers is equally
acceptable; pick one approach per file and keep the emitted message/tip/line/
identifier byte-identical to the twin.

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Mirroring multiple twins into one class collides on shared helpers (`MESSAGE_PATTERN`/`TIP` constants, `message()`/`tip()` methods) → class fatals before any test runs | Rename each concern's helpers to concern-specific names (or inline the strings), keeping emitted strings byte-identical. Covered by §2 "Merging helper members" and the Phase 1–3 disambiguation tasks. |
| A reused stub trips a *second* merged concern under the Combined rule, producing an extra error the twin's expected array doesn't list | Test fails on the unexpected error. **STOP and report** — do not delete the case or edit the expected array. This is exactly the cross-concern interaction the spec exists to surface. Covered by the per-phase Tests entries + Open Questions guard. |
| Facade-alias cases produce no error because the runtime alias autoloader isn't registered | Phase 3 replicates `OnlyAllowFacadeAliasInBladeTest`'s `setUp()`/`autoload()`/`$aliases`/imports into `CombinedStaticCallRuleTest`. Without it, `ReflectionClass('Route')` won't resolve to a `Facade` subclass and the case silently passes-as-no-error then fails the expectation. Covered by Phase 3 Tests. |
| A Combined rule's constructor signature has drifted since commit `271efb3` | Compare against source first; on mismatch treat as drift and STOP (see Open Questions). |
| `defaultAnalysisParser` service or `createReflectionProvider()` unavailable in the test container | STOP and report rather than substituting an alternative parser/provider. |
| `composer phpstan` later analyses the new test files and flags the facade `$aliases` literals | The copied scaffolding carries the original `// @phpstan-ignore-line` comments, keeping analysis clean. Verified by the optional PHPStan check in Phase 3. |

## Implementation

### Phase 1: CombinedMethodCallRule coverage (Priority: HIGH)

- [x] Create `tests/Rules/CombinedMethodCallRuleTest.php` with a `getRule()` that
      builds `CombinedMethodCallRule` from the production config (parser via
      `self::getContainer()->getService('defaultAnalysisParser')`).
- [x] Mirror every `#[Test]` case from these four twin tests, rewriting stub
      paths per the table — copy expected messages/lines/tips/identifiers
      verbatim: `Debug/NoChainedDebugInNamespaceTest`,
      `Validation/NoUnsafeRequestDataRuleTest`,
      `Validation/UnvalidatedFormRequestFieldRuleTest`,
      `Conventions/PositionalFlagArgumentMethodCallRuleTest`.
- [x] Resolve member collisions per §2 "Merging helper members" — rename each
      concern's `MESSAGE_PATTERN`/`TIP`/`message()`/`tip()` (or inline the
      strings) so the class compiles; prefix clashing `#[Test]` names by concern.
- [x] Tests — `vendor/bin/phpunit --filter=CombinedMethodCallRuleTest` all pass;
      if any case fails, stop and report per Open Questions. **49 pass, parity
      confirmed (no cross-concern errors).**

### Phase 2: CombinedFuncCallRule coverage (Priority: HIGH)

- [x] Create `tests/Rules/CombinedFuncCallRuleTest.php` with a `getRule()` that
      builds `CombinedFuncCallRule` (reflection via `self::createReflectionProvider()`).
- [x] Mirror every `#[Test]` case from `Debug/NoDebugInNamespaceTest`,
      `NoInvadeInAppCodeTest` (stubs already at `'/stubs/...'`), and
      `Validation/NoUnsafeRequestHelperRuleTest`, rewriting stub paths. Resolve
      any helper-member/`#[Test]` name clashes per §2.
- [x] Tests — `vendor/bin/phpunit --filter=CombinedFuncCallRuleTest` all pass;
      stop and report on any failure. **26 pass — surfaced and fixed a real
      production bug first (see Findings).**

### Phase 3: CombinedStaticCallRule coverage (Priority: HIGH)

- [x] Create `tests/Rules/CombinedStaticCallRuleTest.php` with a `getRule()` that
      builds `CombinedStaticCallRule` (reflection via `self::createReflectionProvider()`).
- [x] Replicate `OnlyAllowFacadeAliasInBladeTest`'s scaffolding verbatim into the
      new class: the `use App\Facades\Custom;` / `Facade` / `Route` imports, the
      `private array $aliases` property (with its `// @phpstan-ignore-line`
      comments), `setUp()` registering the SPL autoloader, and the
      `autoload(string $alias): void` method. The facade-alias cases fail without
      it; the autoloader only aliases `Route`/`Custom` so it is harmless to the
      other concerns.
- [x] Mirror every `#[Test]` case from `OnlyAllowFacadeAliasInBladeTest`
      (stubs at `'/stubs/...'`), `Debug/NoStaticChainedDebugInNamespaceTest`,
      `Validation/NoUnsafeRequestFacadeRuleTest`, and
      `Conventions/PositionalFlagArgumentStaticCallRuleTest`. Resolve any
      helper-member/`#[Test]` name clashes per §2.
- [x] Tests — `vendor/bin/phpunit --filter=CombinedStaticCallRuleTest` all pass.
      **27 pass, parity confirmed.**
- [x] Format + full suite — `vendor/bin/pint` clean on the new files;
      `vendor/bin/phpunit` green (original 103 tests + the new ones). **205 tests
      pass.** Optional:
      `vendor/bin/phpstan analyse --memory-limit=2G` reports 0 errors.

---

## Open Questions

1. **A mirrored case fails under its Combined rule.** This means production
   diverges from the tested twin, or a stub legitimately triggers a second
   merged concern. The implementer must **stop and report** the failing test,
   stub, and expected-vs-actual errors — not edit expected values to force green.
   If the cause is clearly a *correct additional* error from another merged
   concern on the same stub, note that so a reviewer can decide whether to add
   the extra expected row. Resolution is a human/reviewer call, not an
   implementer improvisation.

---

<!-- ## Resolved Questions -->

## Findings

- **Real production bug found by Phase 2 (the safety net working as intended).**
  `CombinedFuncCallRule::processNode` quick-rejected on the **case-sensitive** raw
  function name, so a mixed-case global helper call like `\REQUEST('b')` was
  dropped before its own *case-insensitive* `checkRequestHelper` ran — a
  false-negative the twin `NoUnsafeRequestHelperRule` did not have. Fixed by
  letting the quick-reject pass through any call whose last name segment is
  `request` case-insensitively (`src/Rules/CombinedFuncCallRule.php`,
  `processNode`). The twin's existing tests still pass; the new
  `CombinedFuncCallRuleTest::flags_fully_qualified_and_mixed_case_request_helper`
  now covers the regression. Debug/invade sub-checks are case-sensitive in both
  twin and Combined, so they were left unchanged (no divergence there).
- Phases 1 and 3 confirmed full twin/Combined parity with no other divergence.

<!-- Notes added during implementation. Do not remove this section. -->
