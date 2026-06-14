# Dedupe Duplicated Rule Logic Into Shared Traits

## Overview

Most checks in this package are implemented twice: once in the unregistered
single-responsibility "twin" rule (which the tests exercise) and once,
copy-pasted, inside the registered `Combined*` rule (which actually ships). The
copies are behaviorally equivalent today — most are exact copy-pastes, a few
differ only cosmetically in source (e.g. the facade message built via
concatenation in the twin vs `sprintf` in the Combined rule, same final string) —
but nothing keeps them in sync, so a fix applied to a twin silently fails to
reach production, and vice versa. This spec extracts each
duplicated check into a shared trait (or `BaseNoDebugRule` method) that **both**
the twin and the Combined rule call, following the pattern already established
by `DetectsPositionalFlagArgument` and `ResolvesFormRequestRuleKeys`. The work
is split into a low-risk Phase 1 and an opt-in, more delicate Phase 2.

**Scope note — this spec deliberately supersedes `plans/002`.** The source plan
`plans/002-dedupe-request-checks-into-traits.md` (and its row in
`plans/README.md`) scopes the work to the three unsafe-request checks only and
marks invade/debug/facade-alias/unvalidated-field explicitly out of scope. After
a per-pair risk review (requested by the maintainer), that scope was widened
here: **Phase 1 (HIGH)** does the plan's original low-risk core (now including
invade and unvalidated-field), and **Phase 2 (LOW, opt-in)** specifies — but does
not require — the delicate remainder. `implement-spec` skips LOW phases by
default, so following this spec end-to-end produces a Phase-1-only change unless
a maintainer explicitly opts into Phase 2. Where this spec and `plans/002`
disagree on scope, **this spec is authoritative.** Written against commit
`271efb3` (2026-06-14).

## Assumptions

<!-- One bullet per AI-introduced inference; sign off by skimming this section. -->

- **Scope = all duplication, phased by risk; widens `plans/002` on purpose** —
  Phase 1 (HIGH) extracts the low-risk, self-contained checks (request
  data/facade/helper, invade, unvalidated-field). Phase 2 (**LOW**, opt-in —
  `implement-spec` skips it by default) extracts the delicate ones that touch
  `BaseNoDebugRule` and runtime-reflection paths (debug orchestration,
  static-debug facade logic, facade-alias). `plans/002` and `plans/README.md`
  describe the narrower request-checks-only scope; this spec supersedes them.
  Chosen after reading every pair; see Resolved Questions for the per-pair risk
  basis.
- **Depends on the Combined-rule tests** — this refactor must not start until the
  tests from `specs/test-registered-combined-rules.md` exist and pass; they are
  the only thing that proves the extraction preserved behaviour for the shipped
  rules. Phase 0 gates on this.
- **Trait names** — `DetectsUnsafeRequestData`, `DetectsUnsafeRequestFacade`,
  `DetectsUnsafeRequestHelper` under `src/Traits/`, mirroring the existing
  `Detects*`/`Resolves*` naming. Naming convention only; open to change.
- **Dependency passing** — checks that need a `ReflectionProvider` or `Parser`
  receive it as a method parameter (both consumers already hold one), rather
  than the trait declaring a constructor. Matches how
  `DetectsPositionalFlagArgument` takes its inputs.
- **Behaviour-preserving** — no rule's public constructor signature changes and
  `extension.neon` is untouched. Existing twin tests and the Combined-rule tests
  are the spec; they must pass unchanged. Editing a test to make it pass means
  behaviour drifted → stop.
- **Positional flag is already deduped** — `DetectsPositionalFlagArgument` is
  shared by both twins and the Combined rules. No action; listed only so the
  ledger is complete.

---

## 1. Current State — the duplicated pairs

Read both sides of each pair before extracting and confirm they are
**behaviorally equivalent** — same checks in the same order producing the same
error message, tip, and identifier for the same input. They need not be
byte-identical in source: cosmetic differences are expected and fine (e.g. the
facade pair below builds its message via concatenation in the twin and `sprintf`
in the Combined rule, yielding the same final string). What must match is the
*behavior*. A genuine behavioral divergence (different check order, a guard
present on one side only, a different emitted string) is a pre-existing bug — do
not paper over it; see Open Questions.

**Low-risk, self-contained (Phase 1):**

- **Unsafe request data** (`MethodCall`):
  `src/Rules/Validation/NoUnsafeRequestDataRule.php:55-123` ↔
  `src/Rules/CombinedMethodCallRule.php:195-256` (`checkUnsafeRequestData` +
  `scopeClassIsRequest`/`typeIsRequest`/`classIsRequest`/`isInRequestNamespace`).
- **Unsafe request facade** (`StaticCall`):
  `src/Rules/Validation/NoUnsafeRequestFacadeRule.php:53-99` ↔
  `src/Rules/CombinedStaticCallRule.php:183-249`. Note the message is built two
  ways (twin concatenates `RequestFacade::class`; Combined uses a `%s::%s()`
  sprintf with `Request::class`) — both resolve to the same FQCN
  `Illuminate\Support\Facades\Request`; the extracted version must emit that
  identical final string.
- **Unsafe request helper** (`FuncCall`):
  `src/Rules/Validation/NoUnsafeRequestHelperRule.php:45-99` (+ `callLabel`) ↔
  `src/Rules/CombinedFuncCallRule.php:135-181`.
- **Invade** (`FuncCall`): `src/Rules/NoInvadeInAppCode.php:33-66` ↔
  `src/Rules/CombinedFuncCallRule.php:114-133` (`checkInvadeUsage`). Tiny, no
  dependencies.
- **Unvalidated form-request field** (`MethodCall`):
  `src/Rules/Validation/UnvalidatedFormRequestFieldRule.php:79-118`
  (`checkUnvalidatedField`) ↔ `src/Rules/CombinedMethodCallRule.php:125-172`
  (`checkUnvalidatedFormRequestField`). The heavy logic already lives in the
  shared `ResolvesFormRequestRuleKeys` trait; only the thin orchestration wrapper
  is duplicated, so this is low risk.

**Delicate (Phase 2):**

- **Static-debug facade logic** (`StaticCall`):
  `src/Rules/Debug/StaticChainedNoDebugInNamespaceRule.php:86-118`
  (`isLaravelStaticDebugCall` + `isFacadeSubclass` + the `facadeReflection`
  field) ↔ the identical private copies in
  `src/Rules/CombinedStaticCallRule.php:211-243`. Substantive duplicated logic;
  drags `ReflectionProvider` and the `Facade` class-reflection setup.
- **Facade-alias** (`StaticCall`): `src/Rules/OnlyAllowFacadeAliasInBlade.php`
  (whole `processNode`) ↔ `src/Rules/CombinedStaticCallRule.php:112-160`
  (`checkFacadeAlias`). Uses runtime `new ReflectionClass()`, a `static $cache`,
  try/catch, and a `// @phpstan-ignore phpstanApi.runtimeReflection, argument.type`
  annotation that must move with the code or PHPStan breaks.
- **Debug wrappers** (`FuncCall`/`MethodCall`/`StaticCall`): the small
  `processNode`/`check*` wrappers in `NoDebugInNamespaceRule`,
  `ChainedNoDebugInNamespaceRule`, `StaticChainedNoDebugInNamespaceRule` ↔ their
  `CombinedFuncCallRule::checkDebugStatement` /
  `CombinedMethodCallRule::checkDebugMethodCall` /
  `CombinedStaticCallRule::checkStaticDebugCall` counterparts. The real logic is
  already in `BaseNoDebugRule`; only the 3–5-line wrappers (message constant +
  identifier prefix per node type) differ, so the marginal drift these carry is
  low — they ride along with the static-debug extraction since they share the
  area.

Existing patterns to model on (same directory, same style):
`src/Traits/DetectsPositionalFlagArgument.php`,
`src/Traits/ResolvesFormRequestRuleKeys.php` (already a `static $cache` trait
shared by a twin and a Combined rule), `src/Traits/ChecksNamespace.php`
(provides `namespaceStartsWithAny`).

Conventions (`CLAUDE.md`): `declare(strict_types=1);` first, `private`
visibility, explicit return types, space after `!`. Copy message `sprintf`/
concatenation as-is — do not restyle, as that changes output.

## 2. Proposed Changes

Each extraction: create the trait method holding the check (returning
`?IdentifierRuleError`), `use` it in the twin **and** the Combined rule, delete
both duplicated bodies, leave each rule's own upstream gating
(`Identifier`/`Name` guards, quick-reject lookups) in place. The existing tests
plus the Combined-rule tests prove behaviour is unchanged after every step.

A `static $cache` moved into a trait method is scoped per using-class on PHP 8.3
(the repo's floor), so the per-class caching in `classIsRequest`,
`OnlyAllowFacadeAliasInBlade`, and the facade reflection is preserved with no
cross-rule sharing — `ResolvesFormRequestRuleKeys` already relies on exactly
this.

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Twin and Combined copies of a check are **behaviorally** different when read (different check order, a one-sided guard, or a different emitted string — *not* mere cosmetic source differences like concat-vs-sprintf) | Stop and report — it may be a latent bug the refactor would otherwise hide by picking one side. Covered by Open Questions. |
| Extracting `static $cache` into a trait changes caching scope | No change on PHP 8.3: trait-method statics are per using-class. Documented in §2; precedent is `ResolvesFormRequestRuleKeys`. |
| Facade message built two different ways collapses into one that changes the emitted string | The trait must emit the identical FQCN string `Illuminate\Support\Facades\Request`; the `NoUnsafeRequestFacadeRuleTest` + `CombinedStaticCallRuleTest` assertions catch any change. |
| Facade-alias extraction drops the `@phpstan-ignore` annotation | The annotation moves with the reflection call; `vendor/bin/phpstan` (Phase 2 gate) fails if it's lost. |
| A behaviour change slips in and a test would need editing to pass | **Stop** — do not edit tests/expected values. A required test edit means the extraction altered behaviour. Covered by Open Questions. |
| Extraction would force a constructor-signature or `extension.neon` change | Out of scope — stop and report. The traits take dependencies as method parameters specifically to avoid this. |

## Implementation

### Phase 0: Confirm the safety net (Priority: HIGH)

- [x] Verify the Combined-rule tests exist and pass —
      `vendor/bin/phpunit --filter=Combined` shows
      `CombinedFuncCallRuleTest`, `CombinedMethodCallRuleTest`,
      `CombinedStaticCallRuleTest` all green. If absent, stop: this spec must not
      proceed without them (see `specs/test-registered-combined-rules.md`).
      **Built via that spec first; 102 Combined tests green.**

### Phase 1: Extract the low-risk checks (Priority: HIGH)

- [x] Create `src/Traits/DetectsUnsafeRequestData.php`; move the request-data
      check (+ `scopeClassIsRequest`/`typeIsRequest`/`classIsRequest`); `use` it
      in `NoUnsafeRequestDataRule` and `CombinedMethodCallRule`, deleting both
      duplicated bodies. Preserve the Combined rule's `quickRejectLookup` gating.
- [x] Create `src/Traits/DetectsUnsafeRequestFacade.php`; reconcile the two
      message styles into one identical string; wire into
      `NoUnsafeRequestFacadeRule` and `CombinedStaticCallRule`.
- [x] Create `src/Traits/DetectsUnsafeRequestHelper.php` (incl. `callLabel`,
      taking `ReflectionProvider` as a parameter); wire into
      `NoUnsafeRequestHelperRule` and `CombinedFuncCallRule`.
- [x] Extract the **invade** check into a shared method/trait
      (`src/Traits/DetectsInvadeUsage.php`); wire into `NoInvadeInAppCode` and
      `CombinedFuncCallRule`.
- [x] Extract the **unvalidated-field** orchestration wrapper into
      `ResolvesFormRequestRuleKeys` (`unvalidatedFormRequestFieldError`); wire
      into `UnvalidatedFormRequestFieldRule` and `CombinedMethodCallRule`.
- [x] Tests — after each extraction, the targeted twin + Combined tests stay
      green (`vendor/bin/phpunit --filter='Request|Invade|Unvalidated|Combined'`);
      no test file is modified. **All green at every step.**

### Phase 2: Extract the delicate checks (Priority: LOW)

<!-- LOW so implement-spec skips it by default — it is genuinely opt-in. Promote
to MEDIUM/HIGH only when a maintainer explicitly decides to take on the
runtime-reflection / BaseNoDebugRule consolidation. -->


- [x] Extract the **static-debug facade logic**
      (`isLaravelStaticDebugCall` + `isFacadeSubclass` + the `facadeReflection`
      setup) into a shared trait taking `ReflectionProvider`; wire into
      `StaticChainedNoDebugInNamespaceRule` and `CombinedStaticCallRule`.
      **Done as `src/Traits/DetectsLaravelStaticDebugCall.php`; the readonly
      `$facadeReflection` field stays in each constructor (cached once) and is
      passed into the trait method — keeps the trait stateless.**
- [x] Extract the **facade-alias** check (runtime `ReflectionClass`, `static
      $cache`, try/catch, `@phpstan-ignore` annotation intact) into a shared
      trait (`src/Traits/DetectsFacadeAlias.php`); wire into
      `OnlyAllowFacadeAliasInBlade` and `CombinedStaticCallRule`.
- [~] Optionally fold the trivial **debug wrappers** (func/chained/static) into
      `BaseNoDebugRule`. **Skipped — the wrappers are 3–5 lines over already-shared
      base helpers; the marginal drift removed doesn't justify touching the base
      class shared by 7 classes. The spec marks this sub-item explicitly skippable.**
- [x] Tests — full suite green (`vendor/bin/phpunit`), no test edits. **205 pass.**

### Phase 3: Final verification (Priority: HIGH)

- [x] `vendor/bin/pint` clean (on package src/tests — pre-existing
      `autoresearch/` fixtures untouched); `vendor/bin/phpunit` green with no
      test-count regression (205); `vendor/bin/phpstan analyse --memory-limit=2G`
      0 errors; `extension.neon` unchanged; existing tests unchanged (only the 3
      new Combined test files added); `grep -rln "DetectsUnsafeRequest" src/Rules`
      lists each new request trait `use`d by exactly two rules; `rector --dry-run`
      0 changes.

---

## Open Questions

1. **A twin and its Combined copy are behaviorally divergent when read** — a
   different check order, a guard on one side only, or a different emitted
   message/identifier for the same input (cosmetic source differences such as
   concat-vs-`sprintf` that produce the identical string are *not* divergence and
   are expected). Stop and report a real divergence — it is a latent bug, and the
   refactor must not silently adopt one side as canonical without a human
   deciding which is correct.
2. **An extraction requires editing a test (or expected value) to stay green.**
   Stop — behaviour changed. The refactor is meant to be transparent; a needed
   test edit is the signal it wasn't.

---

<!-- ## Resolved Questions -->
## Resolved Questions

1. **How much of the duplication should this spec cover — the 3 request checks,
   or all of it?** **Decision:** All of it, split into a low-risk Phase 1 (HIGH)
   and an opt-in Phase 2 (**LOW** — skipped by default by `implement-spec`).
   **Rationale:** A per-pair read showed request×3,
   invade, and unvalidated-field are self-contained and low-risk (the last
   already shares its heavy logic via `ResolvesFormRequestRuleKeys`), while the
   static-debug facade logic, facade-alias reflection, and debug wrappers touch
   `BaseNoDebugRule` and runtime-reflection paths. Two orthogonal axes apply to
   Phase 2: its **priority is LOW** (so `implement-spec` skips it by default — it
   is opt-in), and its **inherent change-risk is MED** (delicate when someone
   does opt in). LOW priority is the default-run decision; MED is the review bar
   once running. Phasing keeps the
   delicate work specified and visible but optional, instead of dropping it.
   Positional flag is already deduped, so it is excluded.

## Findings

- **Seven traits now back the rules; six rule pairs deduplicated.** New traits:
  `DetectsUnsafeRequestData`, `DetectsUnsafeRequestFacade`,
  `DetectsUnsafeRequestHelper`, `DetectsInvadeUsage`, `DetectsFacadeAlias`,
  `DetectsLaravelStaticDebugCall`, plus `unvalidatedFormRequestFieldError` added
  to the existing `ResolvesFormRequestRuleKeys`. Each is consumed by exactly its
  twin and its Combined rule.
- **Trait composition.** The new traits call `namespaceStartsWithAny()` /
  `namespaceStartsWith()` provided by the consumer's `ChecksNamespace` (directly
  on the twins, via `BaseNoDebugRule` on the Combined rules) rather than
  declaring `ChecksNamespace` themselves — avoids a same-trait conflict on the
  Combined rules and matches how `DetectsPositionalFlagArgument` composes.
- **Readonly-class constraint shaped the static-debug extraction.** The Combined
  and twin static rules are `final readonly`, so a lazily-memoised
  `facadeReflection` inside the trait was impossible. Resolved by keeping the
  cached `?ClassReflection $facadeReflection` field in each constructor (set once)
  and passing it into the stateless trait method — preserves the
  resolve-Facade-once behaviour with no per-call reflection.
- **Behaviour preserved.** No test was modified; the 205-test suite (incl. the
  102 Combined tests from `specs/test-registered-combined-rules.md`) stayed green
  through every extraction. PHPStan max + 100% type coverage + Rector all clean.
  No `extension.neon` or constructor-signature change.
- **Debug-wrapper consolidation skipped** (the spec's optional Phase 2 item) —
  see the Phase 2 task note.

<!-- Notes added during implementation. Do not remove this section. -->
