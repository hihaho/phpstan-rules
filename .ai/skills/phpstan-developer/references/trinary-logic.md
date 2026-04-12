# PHPStan TrinaryLogic Reference

## The Core Problem TrinaryLogic Solves

Static analysis cannot always give a definitive yes or no answer. Consider:

```php
function process(mixed $value): void
{
    // Is $value a string? We don't know at compile time.
}
```

A regular `bool` forces you to pick: either you flag this as an error (too noisy) or you let it pass (too permissive). TrinaryLogic captures the third state: **we cannot be certain either way**.

---

## The Three States

| State | Meaning | Returned when |
|-------|---------|---------------|
| `Yes` | Definitely true for all possible runtime values | Type is provably `string`, `int`, etc. |
| `No` | Definitely false for all possible runtime values | Type can never be that kind |
| `Maybe` | True for some runtime values, false for others | Type is `mixed`, a union, or otherwise uncertain |

TrinaryLogic is a **flyweight** — the three instances are singletons compared by identity. You never construct them with `new`; use the factory methods:

```php
TrinaryLogic::createYes()
TrinaryLogic::createNo()
TrinaryLogic::createMaybe()
TrinaryLogic::createFromBoolean(bool $value)  // Yes or No only, never Maybe
```

---

## Reading Results: Which Check to Use

This is the key decision in rule writing. Ask yourself: **"At what level of certainty do I want to report an error?"**

### `->yes()` — Only act when certain

Use when you want **zero false positives**. Only fires if PHPStan has proven the condition is always true.

```php
// Report only when we KNOW $value is always null — never for mixed or nullable types
if ($scope->getType($node)->isNull()->yes()) {
    return [RuleErrorBuilder::message('This is always null.')->...->build()];
}
```

Typical use cases:
- Reporting that a comparison is always true/false
- Flagging that a return type is always `void`
- Enforcing that a variable is definitely defined before use

### `->no()` — Only act when certainly not

Use when you want to **skip nodes that cannot possibly match**. If a type is definitely not an object, no point doing object-related checks.

```php
$type = $scope->getType($node->var);

// Early exit: if it's definitely not an object, skip all object checks
if ($type->isObject()->no()) {
    return [];
}

// Proceed with object-specific logic
```

Typical use cases:
- Early-return guards in `processNode()`
- Skipping reflection lookups when type is provably incompatible
- Confirming a class definitely does not implement an interface

### `->maybe()` — Act under uncertainty (use carefully)

`maybe()` is true only for the middle state — it excludes both `yes()` and `no()`. It means "we have a union type or `mixed` where some branches match and others don't."

```php
// Produce a softer warning: "this might be null" (not "always is")
$result = $scope->getType($node)->isNull();

if ($result->yes()) {
    return [RuleErrorBuilder::message('This is always null.')->...->build()];
}

if ($result->maybe()) {
    return [RuleErrorBuilder::message('This might be null.')->...->build()];
}
```

**Caution:** Checking `maybe()` directly is uncommon in rules. More often you combine checks:

```php
// "Is this NOT definitely safe?" — catches both maybe and yes
if (!$type->isNull()->no()) {
    // could be null (either Maybe or Yes)
}
```

---

## Common Rule Patterns

### Pattern 1: Gate on certainty — report only the definite case

```php
$type = $scope->getType($node);

if ($type->isString()->yes()) {
    // Definitely a string — apply the string-specific rule
}
// If maybe or no — stay silent, the type isn't certain enough to complain
```

### Pattern 2: Skip when definitely not applicable

```php
$type = $scope->getType($node->var);

if ($type->isObject()->no()) {
    return [];  // Can't be an object, nothing to check
}

$classNames = $type->getObjectClassNames();
// ... proceed with reflection
```

### Pattern 3: Two-tier error messages

```php
$nullResult = $scope->getType($node)->isNull();

if ($nullResult->yes()) {
    return [RuleErrorBuilder::message('Value is always null.')
        ->identifier('nullCheck.alwaysNull')->build()];
}

if ($nullResult->maybe()) {
    return [RuleErrorBuilder::message('Value might be null.')
        ->identifier('nullCheck.maybeNull')->build()];
}

return [];  // ->no(): definitely not null, nothing to report
```

### Pattern 4: Check variable is definitely defined

```php
$hasVar = $scope->hasVariableType('myVar');  // returns TrinaryLogic

if ($hasVar->no()) {
    return [RuleErrorBuilder::message('Variable $myVar is undefined.')
        ->identifier('variable.undefined')->build()];
}

if ($hasVar->maybe()) {
    return [RuleErrorBuilder::message('Variable $myVar might not be defined.')
        ->identifier('variable.undefined')->build()];
}

// ->yes(): definitely defined, no error
return [];
```

---

## Logical Operations

TrinaryLogic supports `and()`, `or()`, and `negate()` that propagate uncertainty correctly.

### `->and()` — All must be Yes for Yes; any No gives No

```
Yes  AND Yes   = Yes
Yes  AND Maybe = Maybe
Yes  AND No    = No
Maybe AND Maybe = Maybe
Maybe AND No   = No
No   AND No    = No
```

```php
// Both conditions must hold with certainty
$isObject = $type->isObject();
$isFinal  = TrinaryLogic::createFromBoolean($classReflection->isFinal());

$isDefinitelyFinalObject = $isObject->and($isFinal);
if ($isDefinitelyFinalObject->yes()) { /* ... */ }
```

### `->or()` — Any Yes gives Yes; all No gives No

```
Yes  OR  No    = Yes
Yes  OR  Maybe = Yes
Maybe OR  No    = Maybe
Maybe OR  Maybe = Maybe
No   OR  No    = No
```

```php
// Either condition being true is enough
$isString = $type->isString();
$isInt    = $type->isInteger();

$isScalar = $isString->or($isInt);
if ($isScalar->yes()) { /* definitely string or int */ }
```

### `->negate()` — Flips Yes/No, leaves Maybe as Maybe

```
negate(Yes)   = No
negate(Maybe) = Maybe
negate(No)    = Yes
```

```php
// "Is this NOT a string?"
$isNotString = $type->isString()->negate();
```

---

## TrinaryLogic in Scope Methods

`$scope->hasVariableType()` is the most common scope method returning TrinaryLogic:

```php
$result = $scope->hasVariableType('foo');

// Common decision tree:
if ($result->no()) {
    // Variable is definitely undefined — error
}
if ($result->maybe()) {
    // Variable might not be defined — conditional error
}
// $result->yes() means definitely defined — no error
```

---

## `extremeIdentity` — Unanimous agreement

Returns the shared value if all operands agree; `Maybe` if they differ. Useful when checking a union type where all branches must agree:

```php
// Does every type in a union return the same result?
$result = TrinaryLogic::extremeIdentity(
    $typeA->isString(),
    $typeB->isString(),
    $typeC->isString(),
);
// Yes if all are Yes, No if all are No, Maybe if mixed
```

---

## Quick Decision Guide

```
Do you want zero false positives?         → check ->yes()
Do you want to skip irrelevant nodes?     → check ->no() as an early return
Do you want to warn on uncertainty?       → check ->maybe() (or !->no())
Do you need to combine conditions?        → use ->and() / ->or()
Do you need to invert a result?           → use ->negate()
Are you checking a variable exists?       → $scope->hasVariableType()->no() / ->maybe()
```
