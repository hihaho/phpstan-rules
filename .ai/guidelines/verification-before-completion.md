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
