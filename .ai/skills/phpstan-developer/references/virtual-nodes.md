# PHPStan Virtual Nodes Reference

PHPStan injects virtual nodes into the AST to provide analysis context that PHP-Parser alone does not expose. All virtual nodes implement `PHPStan\Node\VirtualNode`. Use these as the target of `getNodeType()` for rules that need class-level, method-level, or file-level context.

## Quick Reference Table

| Node | When fired | Key accessor |
|------|-----------|--------------|
| `FileNode` | Once per file, after all statements | `->getNodes()` |
| `InClassNode` | Once per class/interface/trait/enum body | `->getClassReflection()` |
| `InClassMethodNode` | Once per method body | `->getMethodReflection()`, `->getClassReflection()` |
| `InFunctionNode` | Once per function body | `->getFunctionReflection()` |
| `ClassPropertyNode` | Once per class property | `->getName()`, `->getClassReflection()` |
| `CollectedDataNode` | Once after all files analysed | `->get(CollectorClass::class)` |
| `ClosureReturnStatementsNode` | End of each closure/arrow fn | `->getReturnStatements()` |
| `MethodReturnStatementsNode` | End of each method | `->getReturnStatements()` |
| `FunctionReturnStatementsNode` | End of each function | `->getReturnStatements()` |
| `BooleanAndNode` | Each `&&` or `and` expression | `->getRightScope()` |
| `BooleanOrNode` | Each `\|\|` or `or` expression | `->getRightScope()` |

All are in the `PHPStan\Node\` namespace.

---

## FileNode

Fired **once per file** at the end of analysis of that file. Use for rules that need to inspect all top-level statements together.

```php
use PHPStan\Node\FileNode;

public function getNodeType(): string
{
    return FileNode::class;
}

public function processNode(Node $node, Scope $scope): array
{
    /** @var FileNode $node */
    $statements = $node->getNodes();  // list<Node\Stmt>
    // ...
}
```

---

## InClassNode

Fired **once per class declaration** (class, interface, trait, enum). The scope has full class context. Use for rules that inspect class-level properties, such as verifying required interfaces or annotations.

```php
use PHPStan\Node\InClassNode;

public function getNodeType(): string
{
    return InClassNode::class;
}

public function processNode(Node $node, Scope $scope): array
{
    /** @var InClassNode $node */
    $classReflection = $node->getClassReflection();
    $originalNode    = $node->getOriginalNode();  // PhpParser\Node\Stmt\ClassLike

    if (!$classReflection->implementsInterface('App\SomeInterface')) {
        return [];
    }
    // ...
}
```

---

## InClassMethodNode

Fired **once per method** after the method body is analysed. Provides both class and method reflection. Use for rules that check method-level constraints (e.g. "this method must be final", "missing return type").

```php
use PHPStan\Node\InClassMethodNode;

public function getNodeType(): string
{
    return InClassMethodNode::class;
}

public function processNode(Node $node, Scope $scope): array
{
    /** @var InClassMethodNode $node */
    $classReflection  = $node->getClassReflection();
    $methodReflection = $node->getMethodReflection();
    $originalNode     = $node->getOriginalNode();  // PhpParser\Node\Stmt\ClassMethod

    if ($methodReflection->getName() === '__construct') {
        return [];
    }
    // ...
}
```

---

## InFunctionNode

Fired **once per function declaration** after its body is analysed.

```php
use PHPStan\Node\InFunctionNode;

public function getNodeType(): string
{
    return InFunctionNode::class;
}

public function processNode(Node $node, Scope $scope): array
{
    /** @var InFunctionNode $node */
    $functionReflection = $node->getFunctionReflection();
    $originalNode       = $node->getOriginalNode();  // PhpParser\Node\Stmt\Function_
    // ...
}
```

---

## ClassPropertyNode

Fired **once per property declaration** in a class (including promoted constructor parameters). Unifies handling of typed/untyped, static/instance, and promoted properties.

```php
use PHPStan\Node\ClassPropertyNode;

public function getNodeType(): string
{
    return ClassPropertyNode::class;
}

public function processNode(Node $node, Scope $scope): array
{
    /** @var ClassPropertyNode $node */
    $name             = $node->getName();              // 'myProperty'
    $classReflection  = $node->getClassReflection();
    $default          = $node->getDefault();           // ?Expr
    $nativeTypeNode   = $node->getNativeTypeNode();    // ?Node (type declaration)
    $isStatic         = $node->isStatic();
    $isReadonly       = $node->isReadonly();
    $isPromoted       = $node->isPromoted();           // constructor promotion
    // ...
}
```

---

## CollectedDataNode

Fired **once after all files are analysed**, containing data gathered by all collectors. See **collectors.md** for full usage.

```php
use PHPStan\Node\CollectedDataNode;
use App\PHPStan\Collectors\MyCollector;

public function getNodeType(): string
{
    return CollectedDataNode::class;
}

public function processNode(Node $node, Scope $scope): array
{
    /** @var CollectedDataNode $node */

    // Skip during partial analysis (individual files passed, not full project)
    if ($node->isOnlyFilesAnalysis()) {
        return [];
    }

    // array<filePath, list<CollectedValue>>
    $data = $node->get(MyCollector::class);
    // ...
}
```

---

## Return Statements Nodes

Three virtual nodes fire at the **end of a callable** with all return statement information:

- `ClosureReturnStatementsNode` — closures and arrow functions
- `MethodReturnStatementsNode` — class methods
- `FunctionReturnStatementsNode` — top-level functions

All implement the `ReturnStatementsNode` interface:

```php
use PHPStan\Node\MethodReturnStatementsNode;

public function getNodeType(): string
{
    return MethodReturnStatementsNode::class;
}

public function processNode(Node $node, Scope $scope): array
{
    /** @var MethodReturnStatementsNode $node */
    $returnStatements = $node->getReturnStatements();  // list<ReturnStatement>
    $statementResult  = $node->getStatementResult();   // control flow
    $executionEnds    = $node->getExecutionEnds();      // list<ExecutionEndNode>
    $hasReturnType    = $node->hasNativeReturnTypehint();

    foreach ($returnStatements as $returnStatement) {
        $returnNode = $returnStatement->getReturnNode();  // PhpParser\Node\Stmt\Return_
        $returnScope = $returnStatement->getScope();      // Scope at the return point
        $expr = $returnNode->expr;
        if ($expr !== null) {
            $type = $returnScope->getType($expr);
        }
    }
    // ...
}
```

---

## BooleanAndNode / BooleanOrNode

Fired for `&&`/`and` and `||`/`or` expressions respectively. Provide the **narrowed scope** applicable to the right-hand side, enabling type-narrowing rules.

```php
use PHPStan\Node\BooleanAndNode;

public function getNodeType(): string
{
    return BooleanAndNode::class;
}

public function processNode(Node $node, Scope $scope): array
{
    /** @var BooleanAndNode $node */
    $originalNode = $node->getOriginalNode();  // PhpParser\Node\Expr\BinaryOp\BooleanAnd
    $rightScope   = $node->getRightScope();    // Scope narrowed after left side is truthy

    $leftType  = $scope->getType($originalNode->left);
    $rightType = $rightScope->getType($originalNode->right);
    // ...
}
```
