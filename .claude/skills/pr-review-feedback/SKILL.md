---
name: pr-review-feedback
description: "Applies PR review feedback with critical evaluation. Activates when: applying review comments, addressing PR feedback, responding to code review, or when user mentions: review feedback, PR comments, apply feedback, address comments, reviewer feedback."
argument-hint: "[PR number]"
---

# Applying PR Review Feedback

Evaluate feedback critically, apply what improves the code, skip what doesn't fit.

## Workflow

### Phase 1: Gather Feedback

1. **Get PR details and review comments**:
   ```bash
   gh api graphql -f query='
   {
     repository(owner: "hihaho", name: "phpstan-rules") {
       pullRequest(number: <NUMBER>) {
         headRefName
         reviewThreads(first: 100) {
           nodes {
             isResolved
             isOutdated
             comments(first: 10) {
               nodes {
                 body
                 author { login }
                 path
                 line
                 diffHunk
                 createdAt
               }
             }
           }
         }
       }
     }
   }'
   ```

2. **Switch to the PR branch**: extract from `headRefName`, then `git checkout && git pull`

### Phase 2: Filter Comments

Only process threads where `isResolved: false`.

If all threads are resolved, report "No unresolved review comments" and stop.

### Phase 3: Evaluate Each Comment

| Consider | Action |
|----------|--------|
| Improves code quality? | Apply it |
| Follows project conventions? | Apply it |
| Subjective preference? | Consider context |
| From automated reviewer? | Evaluate critically |

### Phase 4: Apply Changes

1. Read the relevant file
2. Make the change following project conventions
3. Run `vendor/bin/pint --dirty`

### Phase 5: Verify Quality

Run the `backend-quality` skill:
1. `vendor/bin/pint --dirty`
2. `composer phpstan`
3. `composer test`

### Phase 6: Commit and Push

```bash
git add <specific-files>
git commit -m "Apply PR review feedback"
git push origin <branch-name>
```

## Response Template

```markdown
## Applied Feedback

1. **[File]**: [What was changed]
   - Reviewer comment: [Brief summary]

## Skipped Feedback

1. **[File]**: [Comment summary]
   - Reason: [Why it was skipped]
```
