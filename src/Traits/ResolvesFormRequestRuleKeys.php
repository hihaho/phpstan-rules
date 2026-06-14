<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Parser\ParserErrorsException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Resolves the set of validated field keys from a FormRequest's rules() method.
 *
 * The resolution deliberately bails to "opaque" (null) on anything it cannot
 * statically prove, so the consuming rule reports only when it is certain a key
 * is never validated. Results are cached per class name.
 */
trait ResolvesFormRequestRuleKeys
{
    /**
     * Methods a FormRequest can override to inject or rewrite the validated
     * data, which makes the rules() key set an unreliable picture of what is
     * actually available. A user override anywhere in the class hierarchy
     * (including a shared base class or trait) makes the class opaque; the
     * framework's own defaults do not — every FormRequest inherits these.
     *
     * @var list<string>
     */
    private const array OPAQUE_FORM_REQUEST_METHODS = [
        'prepareForValidation',
        'validationData',
        'all',
    ];

    private const string FRAMEWORK_NAMESPACE_PREFIX = 'Illuminate\\';

    private function classIsFormRequest(string $className): bool
    {
        /** @var array<string, bool> $cache */
        static $cache = [];

        if (! array_key_exists($className, $cache)) {
            $cache[$className] = (new ObjectType(FormRequest::class))
                ->isSuperTypeOf(new ObjectType($className))
                ->yes();
        }

        return $cache[$className];
    }

    /**
     * Flags reading a request field via `$this->accessor('key')` inside a
     * FormRequest when 'key' is never declared in the class's rules(). Shared by
     * the standalone UnvalidatedFormRequestFieldRule and the registered
     * CombinedMethodCallRule so the two cannot drift.
     *
     * The consuming class must provide `namespaceStartsWithAny()` (via the
     * ChecksNamespace trait, directly or through a parent).
     *
     * @param  array<string, true>  $fieldAccessorsLookup
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    private function unvalidatedFormRequestFieldError(
        MethodCall $node,
        string $methodName,
        Scope $scope,
        Parser $parser,
        array $fieldAccessorsLookup,
        array $namespaces,
        array $excludeNamespaces,
    ): ?IdentifierRuleError {
        if (! isset($fieldAccessorsLookup[strtolower($methodName)])) {
            return null;
        }

        if (! $node->var instanceof Variable || $node->var->name !== 'this') {
            return null;
        }

        if (! $this->namespaceStartsWithAny($scope, $namespaces) || $this->namespaceStartsWithAny($scope, $excludeNamespaces)) {
            return null;
        }

        $args = $node->getArgs();

        if ($args === []) {
            return null;
        }

        $keyArg = $args[0]->value;

        if (! $keyArg instanceof String_) {
            return null;
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        if (! $this->classIsFormRequest($classReflection->getName())) {
            return null;
        }

        $validatedRoots = $this->resolveValidatedRoots($parser, $classReflection, $scope);

        if ($validatedRoots === null) {
            return null;
        }

        if (isset($validatedRoots[$this->rootSegment($keyArg->value)])) {
            return null;
        }

        return $this->buildUnvalidatedFieldError($keyArg->value, $methodName);
    }

    private function buildUnvalidatedFieldError(string $fieldKey, string $methodName): IdentifierRuleError
    {
        return RuleErrorBuilder::message(sprintf(
            "Reading '%s' via %s() but the FormRequest's rules() never validates it.",
            $fieldKey,
            $methodName,
        ))
            ->identifier('hihaho.validation.unvalidatedFormRequestField')
            ->tip("Add '{$fieldKey}' to rules(), or if it is intentionally unvalidated suppress with @phpstan-ignore-next-line hihaho.validation.unvalidatedFormRequestField.")
            ->build();
    }

    /**
     * Root segments of every literal key declared in the class's rules() array,
     * resolved once and cached by class name. A null result means rules() is
     * opaque and the whole class must be skipped.
     *
     * @return array<string, true>|null
     */
    private function resolveValidatedRoots(Parser $parser, ClassReflection $classReflection, Scope $scope): ?array
    {
        /** @var array<string, array<string, true>|null> $cache */
        static $cache = [];

        $className = $classReflection->getName();

        if (array_key_exists($className, $cache)) {
            return $cache[$className];
        }

        return $cache[$className] = $this->extractValidatedRoots($parser, $classReflection, $scope);
    }

    /**
     * @return array<string, true>|null
     */
    private function extractValidatedRoots(Parser $parser, ClassReflection $classReflection, Scope $scope): ?array
    {
        if ($this->hasUserDefinedOpaqueMethod($classReflection, $scope)) {
            return null;
        }

        if (! $classReflection->hasMethod('rules')) {
            return null;
        }

        // rules() may be inherited from a base class (or trait), so resolve where
        // it is actually declared and parse that file — not the call site's file,
        // which can be a trait method living elsewhere.
        $declaringClass = $classReflection->getMethod('rules', $scope)->getDeclaringClass();
        $file = $declaringClass->getFileName();

        if ($file === null) {
            return null;
        }

        try {
            $stmts = $parser->parseFile($file);
        } catch (ParserErrorsException) {
            return null;
        }

        $classNode = $this->findClassLikeNode($stmts, $declaringClass->getName());

        if (! $classNode instanceof ClassLike) {
            return null;
        }

        $returnArray = $this->directReturnArray($this->findMethod($classNode, 'rules'));

        if (! $returnArray instanceof Array_) {
            return null;
        }

        $roots = [];

        foreach ($returnArray->items as $item) {
            // PhpParser v5: a spread is an ArrayItem with unpack === true and a
            // value-only entry has key === null; both make the set unresolvable.
            if ($item->unpack || $item->key === null) {
                return null;
            }

            if (! $item->key instanceof String_) {
                return null;
            }

            $roots[$this->rootSegment($item->key->value)] = true;
        }

        return $roots;
    }

    /**
     * True when an opaque method is overridden by user code anywhere in the
     * hierarchy. Methods declared by the framework itself are inherited by every
     * FormRequest and are not treated as overrides — otherwise no class would
     * ever resolve.
     */
    private function hasUserDefinedOpaqueMethod(ClassReflection $classReflection, Scope $scope): bool
    {
        foreach (self::OPAQUE_FORM_REQUEST_METHODS as $methodName) {
            if (! $classReflection->hasMethod($methodName)) {
                continue;
            }

            $declaringClass = $classReflection->getMethod($methodName, $scope)->getDeclaringClass()->getName();

            if (! str_starts_with($declaringClass, self::FRAMEWORK_NAMESPACE_PREFIX)) {
                return true;
            }
        }

        return false;
    }

    private function findMethod(ClassLike $classNode, string $name): ?ClassMethod
    {
        foreach ($classNode->getMethods() as $method) {
            if (strtolower($method->name->name) === $name) {
                return $method;
            }
        }

        return null;
    }

    /**
     * defaultAnalysisParser runs name resolution, so namespacedName is set.
     *
     * @param  array<Stmt>  $stmts
     */
    private function findClassLikeNode(array $stmts, string $className): ?ClassLike
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Namespace_) {
                $found = $this->findClassLikeNode($stmt->stmts, $className);

                if ($found instanceof ClassLike) {
                    return $found;
                }

                continue;
            }

            if ($stmt instanceof ClassLike
                && $stmt->namespacedName instanceof Name
                && $stmt->namespacedName->toString() === $className) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * Only the high-precision case: exactly one top-level `return [...]`.
     * Any return nested in control flow means the keys depend on runtime state,
     * so a conditional rules() is opaque — as is a return-of-variable, a
     * non-array return, or multiple returns.
     */
    private function directReturnArray(?ClassMethod $method): ?Array_
    {
        if (! $method instanceof ClassMethod || $method->stmts === null) {
            return null;
        }

        if ($this->countReturns($method->stmts) !== 1) {
            return null;
        }

        foreach ($method->stmts as $stmt) {
            if ($stmt instanceof Return_) {
                return $stmt->expr instanceof Array_ ? $stmt->expr : null;
            }
        }

        // The single return is nested inside control flow → conditional.
        return null;
    }

    /**
     * Counts return statements, skipping those inside closures used as rule
     * values — only returns that decide what rules() yields should count.
     *
     * @param  array<Stmt>  $stmts
     */
    private function countReturns(array $stmts): int
    {
        $visitor = new class extends NodeVisitorAbstract {
            public int $count = 0;

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof FunctionLike) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof Return_) {
                    ++$this->count;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->count;
    }

    private function rootSegment(string $key): string
    {
        $root = strstr($key, '.', true);

        return $root === false ? $key : $root;
    }
}
