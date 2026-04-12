# PHPStan Extensions Reference

Beyond rules, PHPStan supports extension points for teaching the type system about dynamic behaviour that cannot be inferred from source code alone.

## Extension Type Overview

| Extension | Use case | Service tag |
|-----------|----------|-------------|
| `DynamicMethodReturnTypeExtension` | Custom return type for a method call | `phpstan.broker.dynamicMethodReturnTypeExtension` |
| `DynamicStaticMethodReturnTypeExtension` | Custom return type for a static call | `phpstan.broker.dynamicStaticMethodReturnTypeExtension` |
| `DynamicFunctionReturnTypeExtension` | Custom return type for a function call | `phpstan.broker.dynamicFunctionReturnTypeExtension` |
| `MethodTypeSpecifyingExtension` | Type narrowing after a method assertion | `phpstan.typeSpecifier.methodTypeSpecifyingExtension` |
| `StaticMethodTypeSpecifyingExtension` | Type narrowing after a static assertion | `phpstan.typeSpecifier.staticMethodTypeSpecifyingExtension` |
| `FunctionTypeSpecifyingExtension` | Type narrowing after a function assertion | `phpstan.typeSpecifier.functionTypeSpecifyingExtension` |
| `MethodsClassReflectionExtension` | Add magic methods to a class | `phpstan.broker.methodsClassReflectionExtension` |
| `PropertiesClassReflectionExtension` | Add magic properties to a class | `phpstan.broker.propertiesClassReflectionExtension` |

---

## Dynamic Return Type Extensions

Use when a method/function returns a type that depends on its **arguments** at the call site — for example, `Container::get(MyClass::class)` should return `MyClass`, not `object`.

### Method Return Type

```php
<?php

declare(strict_types=1);

namespace App\PHPStan\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

final class ContainerGetReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    // Which class this extension applies to
    public function getClass(): string
    {
        return \App\Container::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'get';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): ?Type {
        if (!isset($methodCall->getArgs()[0])) {
            return null;  // return null to use default return type
        }

        $argType = $scope->getType($methodCall->getArgs()[0]->value);
        $classNames = $argType->getObjectClassNames();

        if (count($classNames) === 1) {
            return new ObjectType($classNames[0]);
        }

        return null;
    }
}
```

**Registration:**
```neon
services:
    -
        class: App\PHPStan\Type\ContainerGetReturnTypeExtension
        tags:
            - phpstan.broker.dynamicMethodReturnTypeExtension
```

### Static Method Return Type

Same as above but implements `DynamicStaticMethodReturnTypeExtension` and receives `StaticCall` instead of `MethodCall`:

```php
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;

final class MyStaticExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string { return MyClass::class; }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'create';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): ?Type {
        // ...
    }
}
```

**Tag:** `phpstan.broker.dynamicStaticMethodReturnTypeExtension`

### Function Return Type

```php
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;

final class MyFunctionExtension implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'my_function';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope
    ): ?Type {
        // ...
    }
}
```

**Tag:** `phpstan.broker.dynamicFunctionReturnTypeExtension`

---

## Type Specifying Extensions

Use when a method/function **narrows types** based on its return value — for example, `assertIsString($x)` should narrow `$x` to `string` when it doesn't throw.

```php
<?php

declare(strict_types=1);

namespace App\PHPStan\Type;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use PHPStan\Type\StringType;

final class AssertIsStringTypeSpecifyingExtension implements StaticMethodTypeSpecifyingExtension
{
    private TypeSpecifier $typeSpecifier;

    // TypeSpecifier is injected automatically
    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }

    public function getClass(): string
    {
        return \App\Assert::class;
    }

    public function isStaticMethodSupported(
        MethodReflection $methodReflection,
        StaticCall $node,
        TypeSpecifierContext $context
    ): bool {
        // Only handle in truthy context (after the assertion passes)
        return $methodReflection->getName() === 'isString'
            && !$context->null();
    }

    public function specifyTypes(
        MethodReflection $methodReflection,
        StaticCall $node,
        Scope $scope,
        TypeSpecifierContext $context
    ): SpecifiedTypes {
        // Narrow the first argument to string
        return $this->typeSpecifier->create(
            $node->getArgs()[0]->value,
            new StringType(),
            $context,
            $scope
        );
    }
}
```

**Registration:**
```neon
services:
    -
        class: App\PHPStan\Type\AssertIsStringTypeSpecifyingExtension
        tags:
            - phpstan.typeSpecifier.staticMethodTypeSpecifyingExtension
```

**TypeSpecifierContext values:**
- `TypeSpecifierContext::createTruthy()` — when assertion is true (e.g. `if (isString($x))`)
- `TypeSpecifierContext::createFalsy()` — when assertion is false
- `->null()` — context is unknown/unused

---

## Reflection Extensions

Use when a class has **magic methods or properties** (via `__get`, `__call`, etc.) that PHPStan cannot see from the source.

### Methods Extension

```php
<?php

declare(strict_types=1);

namespace App\PHPStan\Reflection;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

final class MagicMethodsExtension implements MethodsClassReflectionExtension
{
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        // Return true if you can provide reflection for this method
        return $classReflection->isSubclassOf(\App\MagicBase::class)
            && str_starts_with($methodName, 'get');
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        // Return a MethodReflection object describing the magic method
        // Use Nette\PHPStan or phpstan-phpunit as reference for custom MethodReflection implementations
        return new MyMagicMethodReflection($classReflection, $methodName);
    }
}
```

**Tag:** `phpstan.broker.methodsClassReflectionExtension`

### Properties Extension

```php
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;

final class MagicPropertiesExtension implements PropertiesClassReflectionExtension
{
    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        return $classReflection->isSubclassOf(\App\MagicBase::class);
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        return new MyMagicPropertyReflection($classReflection, $propertyName);
    }
}
```

**Tag:** `phpstan.broker.propertiesClassReflectionExtension`

---

## Complete Service Tag Reference

```neon
services:
    # Rules and collectors
    - { class: MyRule, tags: [phpstan.rules.rule] }
    - { class: MyCollector, tags: [phpstan.collector] }

    # Dynamic return types
    - { class: MyExt, tags: [phpstan.broker.dynamicMethodReturnTypeExtension] }
    - { class: MyExt, tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension] }
    - { class: MyExt, tags: [phpstan.broker.dynamicFunctionReturnTypeExtension] }

    # Type specifying
    - { class: MyExt, tags: [phpstan.typeSpecifier.methodTypeSpecifyingExtension] }
    - { class: MyExt, tags: [phpstan.typeSpecifier.staticMethodTypeSpecifyingExtension] }
    - { class: MyExt, tags: [phpstan.typeSpecifier.functionTypeSpecifyingExtension] }

    # Reflection
    - { class: MyExt, tags: [phpstan.broker.methodsClassReflectionExtension] }
    - { class: MyExt, tags: [phpstan.broker.propertiesClassReflectionExtension] }

    # Throw types
    - { class: MyExt, tags: [phpstan.broker.dynamicFunctionThrowTypeExtension] }
    - { class: MyExt, tags: [phpstan.broker.dynamicMethodThrowTypeExtension] }
    - { class: MyExt, tags: [phpstan.broker.dynamicStaticMethodThrowTypeExtension] }

    # Closure parameter types
    - { class: MyExt, tags: [phpstan.broker.functionParameterClosureTypeExtension] }
    - { class: MyExt, tags: [phpstan.broker.methodParameterClosureTypeExtension] }
    - { class: MyExt, tags: [phpstan.broker.staticMethodParameterClosureTypeExtension] }

    # Misc
    - { class: MyExt, tags: [phpstan.stubFilesExtension] }
    - { class: MyExt, tags: [phpstan.phpDoc.typeNodeResolverExtension] }
    - { class: MyExt, tags: [phpstan.ignoreErrorExtension] }
    - { class: MyExt, tags: [phpstan.properties.readWriteExtension] }
```

---

## Neon Config Structure for an Extension Package

A reusable PHPStan extension package ships an `extension.neon` file that users include:

```neon
# extension.neon

parameters:
    myExtension:
        someOption: true

parametersSchema:
    myExtension: structure([
        someOption: bool()
    ])

services:
    -
        class: App\PHPStan\Rules\MyRule
        tags:
            - phpstan.rules.rule

    -
        class: App\PHPStan\Type\MyReturnTypeExtension
        tags:
            - phpstan.broker.dynamicMethodReturnTypeExtension
```

Users add to their `phpstan.neon`:

```neon
includes:
    - vendor/my-package/extension.neon
```
