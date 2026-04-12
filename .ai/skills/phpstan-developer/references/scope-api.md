# PHPStan Scope & Reflection API Reference

## Scope Interface

`PHPStan\Analyser\Scope` is passed to every `processNode()` call. It is **immutable** — methods that "filter" the scope return a new instance.

### Context Checks

```php
// File
$scope->getFile(): string                    // Absolute path of file being analysed
$scope->isDeclareStrictTypes(): bool         // strict_types=1 active?

// Class context
$scope->isInClass(): bool                    // Inside a class/interface/trait/enum?
$scope->getClassReflection(): ?ClassReflection  // null if not in class

// Function/method context
$scope->getFunction(): ?FunctionReflection|MethodReflection
$scope->getFunctionName(): ?string           // Short name of current function/method
$scope->isInAnonymousFunction(): bool        // Inside a closure or arrow function?
$scope->getAnonymousFunctionReturnType(): ?Type

// Trait context
$scope->isInTrait(): bool
$scope->getTraitReflection(): ?ClassReflection   // The trait itself (not the using class)

// Namespace
$scope->getNamespace(): ?string              // Current namespace
```

### Variable Types

```php
$scope->hasVariableType(string $name): TrinaryLogic
$scope->getVariableType(string $name): Type

// TrinaryLogic results:
$result->yes()    // definitely true
$result->no()     // definitely false
$result->maybe()  // unknown / conditional
```

### Expression Types

```php
$scope->getType(Expr $expr): Type          // PHPDoc-enhanced type (recommended)
$scope->getNativeType(Expr $expr): Type    // Native type only (ignores @var, @param etc.)
$scope->hasExpressionType(Expr $expr): TrinaryLogic
```

### Type Narrowing

```php
// Returns a new Scope with types narrowed as if $expr evaluated truthy/falsy
$scope->filterByTruthyValue(Expr $expr): Scope
$scope->filterByFalseyValue(Expr $expr): Scope
```

### Name Resolution

```php
// Resolves self/parent/static to fully-qualified class name
$scope->resolveName(Node\Name $name): string
```

### Special Context Guards

```php
// True if inside an `if (class_exists('Foo'))` block — suppress class-not-found errors
$scope->isInClassExists(string $className): bool

// True if inside an `if (function_exists('foo'))` block
$scope->isInFunctionExists(string $functionName): bool

// True if this expression is the target of an assignment ($x = ...)
$scope->isInExpressionAssign(Expr $expr): bool
```

---

## Type Interface

Never use `instanceof` to check PHPStan types. Use the `is*()` methods which handle union types, intersection types, and template types correctly.

### Type Checks (return TrinaryLogic)

```php
$type->isString()
$type->isInteger()
$type->isFloat()
$type->isBool()
$type->isNull()
$type->isArray()
$type->isObject()
$type->isCallable()
$type->isIterable()
$type->isEnum()
```

### Type Extraction

```php
$type->getObjectClassNames(): list<string>          // ['App\Foo', 'App\Bar']
$type->getObjectClassReflections(): list<ClassReflection>
$type->getConstantStrings(): list<ConstantStringType>   // for literal string types
$type->getArrays(): list<ArrayType|ConstantArrayType>
$type->getConstantArrays(): list<ConstantArrayType>     // known-key array shapes
```

### Type Description (for error messages)

```php
use PHPStan\Type\VerbosityLevel;

$type->describe(VerbosityLevel::typeOnly())    // 'string', 'int', 'Foo'
$type->describe(VerbosityLevel::value())       // 'string', '"hello"', '42'
$type->describe(VerbosityLevel::line())        // one-line description
$type->describe(VerbosityLevel::full())        // complete verbose description

// Smart selection based on both types (for "expected X got Y" messages):
VerbosityLevel::getRecommendedLevelByType($expectedType, $actualType)
```

### Type Relationships

```php
// Is $otherType a subtype of $type?
$type->isSuperTypeOf(Type $otherType): TrinaryLogic

// Does $type accept $valueType as a value?
// (e.g. can you assign $valueType to a variable declared as $type?)
$type->accepts(Type $type, bool $strictTypes): AcceptsResult
```

---

## ReflectionProvider

Inject `PHPStan\Reflection\ReflectionProvider` as a constructor parameter.

```php
// Classes
$this->reflectionProvider->hasClass(string $className): bool
$this->reflectionProvider->getClass(string $className): ClassReflection

// Functions
$this->reflectionProvider->hasFunction(Node\Name $nameNode, ?NamespaceAnswerer $ns): bool
$this->reflectionProvider->getFunction(Node\Name $nameNode, ?NamespaceAnswerer $ns): FunctionReflection
$this->reflectionProvider->resolveFunctionName(Node\Name $nameNode, ?NamespaceAnswerer $ns): ?string

// Constants
$this->reflectionProvider->hasConstant(Node\Name $nameNode, ?NamespaceAnswerer $ns): bool
$this->reflectionProvider->getConstant(Node\Name $nameNode, ?NamespaceAnswerer $ns): ConstantReflection
```

Pass `$scope` (which implements `NamespaceAnswerer`) as the second argument for proper namespace resolution.

---

## ClassReflection

Returned by `$scope->getClassReflection()` or `$reflectionProvider->getClass()`.

```php
$classReflection->getName(): string                  // 'App\Foo'
$classReflection->getDisplayName(): string           // 'App\Foo' or 'Foo<Bar>' for generics
$classReflection->getShortName(): string             // 'Foo'

// Class kind
$classReflection->isClass(): bool
$classReflection->isInterface(): bool
$classReflection->isTrait(): bool
$classReflection->isEnum(): bool
$classReflection->isAbstract(): bool
$classReflection->isFinal(): bool
$classReflection->isAnonymous(): bool
$classReflection->isBuiltin(): bool                  // PHP built-in (e.g. stdClass)

// Hierarchy
$classReflection->getParentClass(): ?ClassReflection
$classReflection->getInterfaces(): ClassReflection[]
$classReflection->getTraits(): ClassReflection[]
$classReflection->isSubclassOf(string $className): bool
$classReflection->implementsInterface(string $interfaceName): bool

// Members
$classReflection->hasMethod(string $name): bool
$classReflection->getMethod(string $name, ClassMemberAccessAnswerer $scope): ExtendedMethodReflection
$classReflection->hasProperty(string $name): bool
$classReflection->getNativeProperty(string $name): PropertyReflection
$classReflection->hasConstant(string $name): bool
$classReflection->getConstant(string $name): ClassConstantReflection
$classReflection->getNativeReflection(): \ReflectionClass
```

---

## MethodReflection / FunctionReflection

```php
$methodReflection->getName(): string
$methodReflection->getDeclaringClass(): ClassReflection
$methodReflection->isPublic(): bool
$methodReflection->isProtected(): bool
$methodReflection->isPrivate(): bool
$methodReflection->isStatic(): bool
$methodReflection->isAbstract(): bool
$methodReflection->isFinal(): TrinaryLogic
$methodReflection->getVariants(): list<ParametersAcceptor>
$methodReflection->acceptsNamedArguments(): TrinaryLogic
```
