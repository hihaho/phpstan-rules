---
name: pull-requests
description: "Creates and manages pull requests. Activates when creating PRs, working on existing PRs, writing PR descriptions, or when the user mentions PR, pull request, or merge request."
---

# Pull Request Management

## Repository Information

- **Owner**: `hihaho`
- **Repository**: `phpstan-rules`
- **Default base branch**: `main`

## How to Create PRs

1. Get the current branch name from git
2. Analyze commits with `git log main..HEAD --oneline`
3. Get the diff with `git diff main...HEAD --stat`
4. Create the PR using `mcp__github__create_pull_request` with:
   - `owner`: `hihaho`
   - `repo`: `phpstan-rules`
   - `head`: current feature branch
   - `base`: `main`
   - `title`: formatted per the title format below
   - `body`: formatted per the template below

## How to Work on Existing PRs

1. **Get PR details** using `mcp__github__get_pull_request`
2. **Switch to the branch**: `git checkout <branch-name>`
3. **Pull latest changes**: `git pull origin <branch-name>`
4. **Apply changes**: Make code changes, write/update tests, run quality checks
5. **Commit and push**: Create meaningful commits and push

## PR Title Format

```
Short descriptive title
```

- Keep the title concise (under 70 characters)
- Use imperative mood ("Add rule" not "Added rule")

### Examples

```
Add NoEmptyCallRule for enforcing explicit checks
Fix false positive in SlashInUrl for root routes
Update EloquentApiResources to support custom collections
```

## PR Description Template

```markdown
## Summary

<1-3 sentence summary of what this PR accomplishes>

## Changes

* <Change 1>
* <Change 2>
* <Change 3>

## Test coverage

- <What test scenarios are covered>
```

## Required Information - Ask If Missing

Before creating a PR, ensure you have:
- Commits/changes to include
- Clear description of what changed and why

## Quality Gate

Before creating a PR, all quality checks must pass:
- `vendor/bin/pint --dirty` — no changes
- `composer phpstan` — 0 errors
- `composer test` — 0 failures
