---
name: evaluate
description: "Evaluate the entire implementation and fix any issues that you find. If you find any issues, please fix them yourself. Only ask the user when you need a decision. Activates when: evaluating implementation, self-reviewing code, checking for issues, or when user mentions: evaluate, check implementation, self-review, verify implementation."
argument-hint: "[file path, rule name, or description of what to evaluate]"
---

# Evaluate Implementation

A self-directed loop: evaluate your own work, fix what you find, re-evaluate until clean.

## When to Use This Skill

- After implementing a new rule or fixing a bug
- When the user says "evaluate", "check this", or "review your work"
- Before creating a PR or marking work as done

## Workflow

### Phase 1: Run Quality Checks

Run the `backend-quality` skill:
1. `vendor/bin/pint --dirty`
2. `composer phpstan`
3. `composer test`

Fix all failures before continuing.

**Skip criteria**: If a check already passed clean earlier in this conversation and no files were changed since, you may skip it. State which checks you're skipping and why.

### Phase 2: Review for Issues

Read through all changed files and check for:

| Category | What to look for |
|----------|-----------------|
| **False positives** | Does the rule flag valid code? |
| **False negatives** | Does the rule miss invalid code? |
| **Edge cases** | Null handling, empty strings, nested structures |
| **Logic errors** | Wrong conditions, incorrect node type checks |
| **Missing tests** | Untested scenarios (positive and negative) |
| **Convention violations** | Deviations from project patterns (check sibling files) |

### Phase 3: Fix Issues

For each issue found:
1. Fix it yourself
2. Run the affected tests to verify
3. If it requires a design decision, ask the user

### Phase 4: Re-evaluate

After fixes, re-run quality checks. Repeat until clean.

### Phase 5: Code Review

Once clean, run the `code-review` skill for a fresh-eyes pass. Fix any findings.

### Phase 6: Report

```markdown
## Evaluation Summary

### Issues Found & Fixed
1. **[Issue]** — [What was wrong and how you fixed it]

### Verified
- All tests pass
- PHPStan clean
- Code style clean

### No Issues Found In
- [Categories that were clean]
```

## Guidelines

- **Fix, don't report** — catch and fix issues, don't just list them
- **Loop until clean** — don't stop after the first fix pass
- **Run tests after every fix**
- **Trust existing patterns** — follow what the codebase does consistently
