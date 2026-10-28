# PHPStan Rules Package

This is `hihaho/phpstan-rules`, a PHPStan extension package that enforces hihaho's Laravel coding conventions through static analysis. This is **not** a Laravel application — it is a standalone Composer package.

## Project Structure

```
src/
├── Rules/             # PHPStan rules (one class per rule)
│   └── Debug/         # Debug statement detection rules
├── Traits/            # Shared traits (ChecksNamespace)
tests/
├── Rules/             # Tests for each rule (PHPStan RuleTestCase)
│   └── stubs/         # Test fixture PHP/Blade files
extension.neon         # PHPStan extension registration
phpstan.neon.dist      # PHPStan analysis configuration
pint.json              # Laravel Pint code style config
```

Class-naming and routing conventions are enforced by the sister package
[hihaho/rector-rules](https://github.com/hihaho/rector-rules), which
auto-fixes them rather than just reporting.

## Dependencies

- **PHP**: ^8.4
- **PHPStan**: ^2.1
- **Laravel Support** (illuminate/support): ^12.0||^13.0
- **Testing**: PHPUnit ^12.5
- **Code Style**: Laravel Pint ^1.21

## Development Commands

```bash
composer test                # Run PHPUnit tests
composer fix-cs              # Run Laravel Pint formatter (alias: composer format)
composer phpstan             # Run PHPStan analysis
composer phpstan-clear-cache # Clear PHPStan cache
composer rector              # Run Rector transformations
composer qa                  # Run format, rector, phpstan, test
```

## Repository

- **Owner**: `hihaho`
- **Repository**: `phpstan-rules`
- **Default branch**: `main`

---

## PHP Conventions

- Always use `declare(strict_types=1);`
- Use `private` visibility by default for methods, properties, and constants
- Always use curly braces for control structures, even for single-line bodies
- Use PHP 8 constructor property promotion
- Always use explicit return type declarations
- Use appropriate PHP type hints for all method parameters
- Add a space after the unary not operator: `if (! $foo)`
- Omit docblocks when methods are fully type-hinted
- Prefer string interpolation over `sprintf` and concatenation
- Never use `empty()` — use explicit checks instead
- Prefer PHPDoc blocks over inline comments

---

## Testing

- Tests use PHPStan's `RuleTestCase` base class
- Each rule has a corresponding test in `tests/Rules/`
- Test stubs (fixture PHP/Blade files) live alongside the tests in `stubs/` subdirectories
- Run all tests: `composer test`
- Run specific test: `vendor/bin/phpunit --filter=TestClassName`
- Every change must have test coverage — write or update tests, then run them

## Creating a New Rule

1. Create the rule class in `src/Rules/` implementing PHPStan's `Rule<T>` interface
2. Register it in `extension.neon` — under `rules:` if the rule has no constructor dependencies, or under `services:` with the `phpstan.rules.rule` tag when it needs DI (e.g. `ReflectionProvider`)
3. Create test stub files in `tests/Rules/stubs/` (or a sibling `stubs/` directory next to the test)
4. Create a test extending `PHPStan\Testing\RuleTestCase` in `tests/Rules/`
5. Use the `ChecksNamespace` trait if the rule needs to filter by namespace prefix
6. Run `composer fix-cs` and `composer phpstan` before finalizing

## Quality Standards

- PHPStan level: `max`
- Type coverage: 100% (return, param, property, declare)
- Cognitive complexity: class max 35 (allows combined rules), function max 10
- Code style: Laravel Pint with `laravel` preset

---

# Release Automation

## CHANGELOG.md is updated automatically — do NOT edit by hand for releases

`CHANGELOG.md` is kept in sync with GitHub releases by `.github/workflows/update-changelog.yml`. When a release is published (not just drafted), the workflow uses `stefanzweifel/changelog-updater-action` to prepend the release body to `CHANGELOG.md` and opens a PR back to the release's target branch.

This means:

- **Do not** add changelog entries manually when preparing a release. The release body (drafted in `internal/release-notes-<version>.md` and pasted into the GitHub release) becomes the changelog entry automatically.
- **Do not** include a changelog diff in the release PR — the post-release PR comes from CI.
- If the changelog needs a fix *after* a release, edit `CHANGELOG.md` directly and commit — but this is unusual and only for typos or formatting issues in the auto-generated entry.

## Release workflow (summary)

1. Draft release notes in `internal/release-notes-<version>.md`
2. Commit and push code + notes file to `main`
3. Tag and create the GitHub release with the release-notes file as the body
4. CI automatically prepends the release body to `CHANGELOG.md` and opens a PR to merge it

No manual `CHANGELOG.md` edits are part of the release PR.

---

## Verification Before Completion

Before claiming any work is complete or successful, run the verification command fresh and confirm the output. Evidence before claims, always.

### Required Before Any Completion Claim

1. **Run** the relevant command (in the current message, not from memory)
2. **Read** the full output
3. **Confirm** it supports the claim
4. **Then** state the result with evidence

### During Development (after each change)

| Claim            | Required verification                              |
|------------------|----------------------------------------------------|
| Code style clean | `vendor/bin/pint --dirty --format agent` output    |
| Tests pass       | Related tests pass via `--filter` or specific file |
| Bug fixed        | Previously failing test now passes                 |

### At Completion Only (feature/phase done, before PR)

These are slow checks — only run them once at the very end:

| Claim             | Required verification                                           |
|-------------------|-----------------------------------------------------------------|
| Rector ran clean  | `vendor/bin/rector process` showing 0 changes                   |
| PHPStan clean     | `vendor/bin/phpstan analyse --memory-limit=2G` showing 0 errors |
| Full suite passes | `vendor/bin/phpunit` output showing 0 failures                  |
| Feature complete  | All above checks pass                                           |

### Always Capture Command Output

Append `|| true` to all verification commands so the output is always visible, even on failure. Without it, a non-zero exit code can hide the output.

```bash
vendor/bin/phpunit --filter=TestName || true
vendor/bin/pint --dirty --format agent || true
```

### Never Use Without Evidence

- "should work now" / "that should fix it" / "looks correct"

These phrases indicate missing verification. Run the command first.

---

## AskUserQuestion Phrasing

When writing an `AskUserQuestion` question, option labels, or option descriptions, **avoid first- and second-person pronouns** — `I`, `me`, `my`, `we`, `our`, `you`, `your`. In that tool the user is reading a question *from* the assistant and answering it, so the roles are inverted and these pronouns are ambiguous: the reader cannot tell whether `I`/`my` means the assistant or themselves, nor whether `you`/`your` means them or the assistant.

Name the actor explicitly instead — "the assistant" (these guidelines are shared across agents, so avoid hard-coding a product name like Claude or Copilot) and "the user" (or a concrete role) for the person answering — or rephrase to drop the pronoun entirely.

```text
❌ "Which approach do you want me to take?"
❌ "Should I keep the existing tests you wrote?"

✅ "Which approach should the assistant take?"
✅ "Keep the existing tests, or replace them?"   (pronoun dropped)
✅ "Should the assistant keep the tests already in the repo?"
```

This applies to every part of the question payload: the `question` text, each option `label`, and each option `description`.

---

## Fixing PHPStan Errors

When fixing a PHPStan error, first decide whether it represents a runtime bug a test could catch — and if so, write that test before the fix.

### Process

1. **Assess testability** — does the error represent a runtime bug a test could reproduce (a wrong argument type, a missing method, an incorrect return type used downstream)?
2. **Write the test first** — if a test can catch it, write a failing test that reproduces the error before applying the fix.
3. **Fix the code** — apply the fix so both the PHPStan error and the new test pass.
4. **Verify both** — confirm PHPStan reports no error and the test passes.

### When to Write a Test

Write a test when the PHPStan error indicates a fault that would surface at runtime:

- A method call on a value of the wrong type
- Missing or incorrect arguments to a function or method
- A return-type mismatch that would break callers
- Accessing a property or method that does not exist
- Any type error that would manifest as a runtime exception

### Annotate Rather Than Suppress

Some errors are PHPStan reading a signature that says less than the code does — a return type a parameter decides, a bool helper that proves a type. The `backend-quality` skill carries the two annotations that state the missing fact, and the rules for when each one lies.

### When to Skip the Test

Skip the test when the error is purely static and cannot cause a runtime failure:

- Missing return-type declarations
- PHPDoc mismatches with no runtime impact
- Unused variables or imports
- Generic-type parameter issues

---

## Signed Commits

Applies **only when the repository has commit signing enabled** (e.g. `git config commit.gpgsign` is `true`, or a `user.signingkey` / `gpg.format` is set). If signing is not enabled, this guideline does not apply — commit normally.

### Never fall back to an unsigned commit

When signing is enabled, every commit must be signed. If the signing backend or agent (1Password, `gpg-agent`, `ssh-agent`, a hardware key, etc.) is unavailable, locked, or not responding:

- **Stop and surface the failure** to the user with the exact error.
- **Do not** retry with `--no-gpg-sign`, unset `commit.gpgsign`, or otherwise produce an unsigned commit to "get past" the problem.

A missing signature is a blocker to resolve (unlock the agent, re-authenticate 1Password, plug in the key), not a step to skip. Let the user fix the signing setup, then commit signed.

---

## Task Scope and Edits

For session, branch, and PR scope, see the `single-issue-scope` guideline when the project enables it.

### The Task Sets the Scope

- Do not fix a pre-existing bug, a performance problem, or unrelated behaviour you find on the way, unless the requested behaviour cannot work without it. The same holds for refactors, cleanup, and documentation nobody asked for. A defect your own change introduces is not pre-existing: fix it.
- A sibling rule that requires an update on a line you already change still applies. The rule removes extras, not obligations.
- Report the rest as a follow-up in your summary. Propose an issue when the project tracks work that way, and let the user decide whether to file it. Report it; do not fix it.
- Implement every behaviour the task does ask for, completely. This rule cuts extras, never the requested scope.

### One Reading of an Ambiguous Ask

Implement the reading that the wording and the surrounding code support most directly. State that assumption in your summary. Do not build for both readings.

Materially different work is the test. When two readings would produce the same change, pick one and carry on. When they would not, or when a wrong guess is unsafe or makes the work useless, ask before building — through the `clarify` skill where the whole ask is fuzzy, otherwise with a direct question.

### Edit in Place

Change only the lines that must change. Rewrite a whole file only when the file is short, or when most of it changes. A rewrite churns lines the task never touched and can drop content by accident.

---

## Voice — Which Rule, Which Surface

This table decides which rule applies to a piece of text. Never apply both to the same words, and never guess.

| Surface | Rule |
|---|---|
| Chat replies to the user | Simplified Technical English |
| Messages between agents — a peer message, a subagent prompt, a subagent report, a handoff note | Simplified Technical English. Never run a `humanizer` pass: machine-to-machine text needs no human voice |
| PR titles, descriptions, checklists | Simplified Technical English |
| PR review comments and replies to reviewers | Simplified Technical English |
| Issue and ticket descriptions, comments, QA testables | Simplified Technical English |
| Spec files | Simplified Technical English |
| `AskUserQuestion` questions, options, descriptions | Simplified Technical English — plus the pronoun rules in the `AskUserQuestion Phrasing` guideline, when the project has it |
| Commit messages | Simplified Technical English — a prefix or token the project's commit format requires stays as it is: an issue key, or a `type(scope)!:` prefix |
| Text an end user reads — in-app copy, translations, release notes, help text, seed content | The project's own tone-of-voice rules, not this guideline |
| Suggested translation strings inside an issue or ticket | The project's own tone-of-voice rules — the prose around them stays Simplified Technical English |
| Code and code comments | Neither — the language guidelines own those |
| Prose the user asks for in a named style, or an artifact whose own skill defines its voice — `readme`, `release-notes`, or `humanizer` on an essay, post or opinion piece | That instruction or skill wins. This guideline does not override it. On the surfaces above, `humanizer` only removes AI tells and this guideline keeps the tone |

A surface the table does not list gets Simplified Technical English, unless an end user reads it. Then it gets the project's tone-of-voice rules. A project without documented tone-of-voice rules gets Simplified Technical English everywhere.

This guideline governs **how a sentence is built**, and how much you write. It never overrides what a document is allowed to say: an issue-format doc still owns issue content, and a PR template still owns its sections.

### Simplified Technical English

**Write in ASD-STE100 Simplified Technical English.** Say the same thing in fewer, simpler words.

- One idea per sentence. Keep procedural sentences to 20 words or less, descriptive sentences to 25 or less. These limits are a ceiling, not a target.
- Vary sentence length. A run of short sentences of the same size reads as machine output, as a run of long ones does. Join two clauses that carry one idea with "because", "so", or "although" instead of splitting them into two short sentences.
- Use the active voice. Name the actor. Use the passive only when the actor is unknown.
- Use simple tenses only — simple present, simple past, simple future, infinitive, imperative. No complex constructions built from auxiliary verbs.
- Use one word for one meaning. Use the same word for the same thing every time — do not vary it for style.
- Keep articles (`the`, `a`, `an`) and other small words that make a sentence clear. Simplified is not clipped.
- One topic per paragraph, six sentences at most. Use a list when there is more than one item.
- Cut filler, hedging, and repetition. Do not restate the question or summarise what you are about to say.
- Give the answer first. Add detail after it, and only if the reader needs it.
- Use everyday words. Write "use", not "utilise"; "help", not "facilitate". Keep technical terms exact — a class name, a flag, or an error message is quoted as it is.
- Write Latin abbreviations out: "for example", not "eg"; "that is", not "ie"; "and so on", not "etc".
- Do not shout. No exclamation marks, no capitals for emphasis, and no bold used only to raise the volume. Structural bold that a template defines — `**Before:**`, `**Expected:**`, a table header, a labelled line — is not emphasis and stays.
- No metaphors, no clichés, no jokes that carry meaning the plain sentence does not.

The sentence limits, the tense list, the article rule, and the paragraph limit come from the ASD-STE100 writing rules. The everyday-words, Latin-abbreviation, no-shouting, and no-metaphor rules come from the GOV.UK content style guide.

### Register and Volume

Simplified Technical English decides how a sentence is built. This section decides how much you write and how you format it. A reply can pass every rule above and still read as machine output, because it is ten times the size of the question and formatted as a report.

**Answer at the size of the question.** A one-line question gets a one-line answer. A yes/no question gets "yes" or "no", and a reason only if the reader cannot act without it. Do not pad a short answer to look thorough. A longer answer does not show more care, and the reader has to find the answer inside it. A sentence that names what you did not verify is content, not padding, and stays.

**Do not format someone else's thread like a document.** In a PR comment, an issue comment, a chat reply, or a review reply, do not use bold section headings, tables, or fenced evidence blocks unless the reader asked for detail or the content cannot be read without them. A code block that quotes real output or a real diff is content and stays. The rest reads as machine output whatever the words are worth.

**One answer per turn.** Do not answer a question and then add a closing observation about what the work taught you. Stop when the answer is complete.

**Read the thread again as the last step before you post.** The thread can move while you draft. A reply to a question the other person already withdrew costs more than a slow reply.

Apply these rules most strictly outside your own repository. A maintainer who does not know you can reject a contribution on this basis alone, and the change itself is then no longer read on merit.

---

# Package Boost Guidelines

These guidelines replace Laravel Boost's default foundation for
repositories that ship as Composer packages — Laravel-targeted or
framework-agnostic. The framing, tooling, and trade-offs differ from
application development; follow this version when working inside a
package codebase.

## Foundational Context

This codebase is a **Composer package**, not an application. The rules
below hold regardless of which framework (if any) the package targets.

- There is no `app/`, `bootstrap/`, `routes/`, `.env`, or database by
  default. Tooling that assumes an application context (e.g. running
  `php artisan` against the package itself) does not apply.
- The primary artefact is the package's public API — entry-point
  classes, service providers, exposed contracts. Everything else is
  scaffolding.
- Downstream consumers depend on this package via Composer. Every
  public change is a user-facing API change governed by semver.
- `composer.json` is the source of truth for supported PHP versions
  and any framework constraints. Check `require.php` (and any
  `require.<framework>/*` entries) before using version-specific
  features.

## Source Layout

- `src/` — package source, PSR-4 autoloaded per `composer.json`
- `tests/` — Pest or PHPUnit suite
- `config/` — publishable defaults shipped with the package, when
  applicable
- `resources/` — views, translations, Boost skills / guidelines, when
  applicable
- `database/migrations`, `database/factories` — only if the package
  ships them
- `workbench/` — developer-only Testbench scaffolding when Testbench
  is in use; never shipped

Check sibling files before inventing structure. Do not introduce new
top-level directories without a clear reason.

## Tests Are the Specification

The package has no running application to click through. Tests are how
behaviour is pinned down.

- Write tests alongside any behavioural change.
- Do not create "verification scripts" when a test can prove the same
  thing.
- Run the project's configured test runner (`vendor/bin/pest` or
  `vendor/bin/phpunit`) before claiming a change is done.

## Public API Discipline

- Every `public`, `protected`, or exported symbol is part of the
  package's surface. Breaking changes require a major version bump.
- Prefer `final` classes and `private`/`@internal` markers for
  anything not intended for extension.
- Keep config keys, published asset paths, and service container
  bindings stable across patch and minor versions.

## Conventions

- Match existing code style, naming, and structural patterns — check
  sibling files before writing new ones.
- Use descriptive names (`resolvePublishDestination`, not `resolve()`).
- Reuse existing helpers before adding new ones.
- Do not add dependencies without approval; every new `require` is a
  constraint downstream consumers inherit.

## Extending boost-core

If your package authors a custom `FileEmitter` (to write a file like
`.mcp.json` into the host during `boost sync`), declare the
`boost-extension` tag in your `boost.php` `withTags([...])`. That pulls
the `writing-file-emitter` skill — gated off by default so consumers
who do not extend the engine don't carry it, which is why an
emitter-authoring package has to opt in explicitly. The same tag pulls
`skill-authoring` for writing boost-family skills.

## Documentation Files

Only create or edit documentation (README, CHANGELOG, docs/) when
explicitly requested or when a behaviour change requires it.

## Replies

Be concise. Focus on what changed and why. Skip restating what the
diff already shows.
