---
name: code-review
description: "Reviews recent code changes for improvements across functionality, code quality, and testing. Activates when: the user asks to review implementation, review changes, review code, audit code, check for improvements, or when user mentions: review, audit, improvements, code quality, code review."
argument-hint: [file path, rule name, or description of changes]
---

# Code Review

A structured review of recent code changes, evaluating them across multiple quality dimensions.

## Workflow

### Phase 1: Identify the Scope

1. **If given specific files**: read those files
2. **If given a rule name**: find the rule, its test, and its stubs
3. **If no scope specified**: use `git diff` to find recently changed files

Read ALL files in scope before starting the review.

### Phase 2: Review Each Dimension

#### Functionality

- **Logic errors**: Conditions that don't match intent, wrong node types checked
- **Missing edge cases**: Null handling, empty strings, boundary conditions
- **PHPStan API misuse**: Wrong method calls on reflection objects, incorrect node class checks
- **False positives**: Does the rule flag code that should be valid?
- **False negatives**: Does the rule miss code that should be flagged?

#### Code Quality

- **Project convention violations**: Check sibling files for patterns
- **Unnecessary complexity**: Could the same result be achieved more simply?
- **Type safety**: Missing return types, loose comparisons
- **Naming**: Do names clearly communicate intent?
- **Error identifiers**: Are error identifiers unique and follow the `hihaho.*` pattern?

#### Testing

- **Missing positive tests**: Valid code that should NOT trigger the rule
- **Missing negative tests**: Invalid code that SHOULD trigger the rule
- **Missing edge cases**: Boundary values, special characters, nested structures
- **Stub coverage**: Do stubs cover all the scenarios the rule handles?

### Phase 3: Compile Findings

1. Group by category
2. Include file + line number
3. Skip categories with no findings

### Phase 4: Prioritize

| Severity | Meaning |
|----------|---------|
| High | False positives/negatives, logic errors |
| Medium | Missing test coverage, convention violations |
| Low | Minor improvements, naming tweaks |

## Guidelines

- **Be concrete**: Every finding must point to a specific line or pattern.
- **Be actionable**: Each finding should make clear what needs to change.
- **Respect existing conventions**: If the codebase does something consistently, follow it.
- **Don't pad**: Skip categories with no findings.
- **Read before reviewing**: Never review code you haven't read.
