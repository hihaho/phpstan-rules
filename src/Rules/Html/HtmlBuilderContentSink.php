<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Html;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * spatie/laravel-html escapes attribute values and `->text()`, and nothing
 * else: `html()->a($href, $contents)`, `->span($contents)`, `->html($html)` and
 * `->children($children)` all render their content argument as raw HTML. This
 * checks that argument wherever it is passed, on the builder, an element, or the
 * `Html` facade.
 *
 * Called from CombinedMethodCallRule and CombinedStaticCallRule, so the check
 * adds no dispatch of its own.
 */
final readonly class HtmlBuilderContentSink
{
    use ChecksNamespace;

    /**
     * Method => position and name of the argument rendered without escaping.
     * The `Html` builder methods wrap `->html()` or `->children()` on a new
     * element; `mailto()`/`tel()` and `submit()`/`reset()` delegate to `a()` and
     * `button()`.
     */
    private const array CONTENT_ARGUMENTS = [
        'a' => [1, 'contents'],
        'button' => [0, 'contents'],
        'div' => [0, 'contents'],
        'fieldset' => [0, 'legend'],
        'i' => [0, 'contents'],
        'label' => [0, 'contents'],
        'legend' => [0, 'contents'],
        'mailto' => [1, 'text'],
        'p' => [0, 'contents'],
        'reset' => [0, 'text'],
        'span' => [0, 'contents'],
        'submit' => [0, 'text'],
        'tel' => [1, 'text'],
        'html' => [0, 'html'],
        'addchild' => [0, 'child'],
        'addchildren' => [0, 'children'],
        'child' => [0, 'child'],
        'children' => [0, 'children'],
        'prependchild' => [0, 'child'],
        'prependchildren' => [0, 'children'],
        'setchildren' => [0, 'children'],
    ];

    /**
     * Methods taking a `$mapper` as their second argument.
     */
    private const array MAPPER_METHODS = [
        'addchild' => true,
        'addchildren' => true,
        'child' => true,
        'children' => true,
        'prependchild' => true,
        'prependchildren' => true,
        'setchildren' => true,
    ];

    /**
     * Strings, not ::class: spatie/laravel-html is not a dependency of this package.
     */
    private const array BUILDER_CLASSES = [
        'Spatie\Html\Html',
        'Spatie\Html\BaseElement',
    ];

    private const string FACADE_CLASS = 'spatie\html\facades\html';

    /**
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        private array $namespaces,
        private array $excludeNamespaces,
        private HtmlSafetyJudge $judge,
    ) {}

    public function isContentMethod(string $methodName): bool
    {
        return $this->namespaces !== [] && isset(self::CONTENT_ARGUMENTS[strtolower($methodName)]);
    }

    public function methodCallError(MethodCall $node, string $methodName, Scope $scope): ?IdentifierRuleError
    {
        if (! $this->isContentMethod($methodName) || ! $this->isInCheckedNamespace($scope) || ! $this->isBuilder($node->var, $scope)) {
            return null;
        }

        return $this->contentError($node->getArgs(), $methodName, $scope);
    }

    public function staticCallError(StaticCall $node, string $methodName, Scope $scope): ?IdentifierRuleError
    {
        if (! $this->isContentMethod($methodName) || ! $node->class instanceof Name || $node->class->toLowerString() !== self::FACADE_CLASS) {
            return null;
        }

        if (! $this->isInCheckedNamespace($scope)) {
            return null;
        }

        return $this->contentError($node->getArgs(), $methodName, $scope);
    }

    /**
     * @param  array<Arg>  $args
     */
    private function contentError(array $args, string $methodName, Scope $scope): ?IdentifierRuleError
    {
        if (isset(self::MAPPER_METHODS[strtolower($methodName)]) && $this->hasMapper($args)) {
            return null;
        }

        $content = $this->contentArgument($args, $methodName);

        if (! $content instanceof Expr || $this->judge->isSafe($content, $scope)) {
            return null;
        }

        return RuleErrorBuilder::message("{$methodName}() renders its content without escaping it, and this value can contain HTML.")
            ->identifier('hihaho.html.unescapedBuilderContent')
            ->tip('Pass the value through ->text() or e() first. If it is trusted HTML, add @phpstan-ignore hihaho.html.unescapedBuilderContent (reason).')
            ->build();
    }

    /**
     * @param  array<Arg>  $args
     */
    private function contentArgument(array $args, string $methodName): ?Expr
    {
        [$position, $name] = self::CONTENT_ARGUMENTS[strtolower($methodName)];

        foreach ($args as $index => $arg) {
            if ($arg->name instanceof Identifier ? $arg->name->name === $name : $index === $position) {
                return $arg->value;
            }
        }

        return null;
    }

    /**
     * With a mapper, the children are the mapper's output, not the values
     * passed in. The builder calls inside the mapper are checked on their own.
     *
     * @param  array<Arg>  $args
     */
    private function hasMapper(array $args): bool
    {
        foreach ($args as $index => $arg) {
            if ($arg->name instanceof Identifier ? $arg->name->name === 'mapper' : $index === 1) {
                return true;
            }
        }

        return false;
    }

    private function isBuilder(Expr $receiver, Scope $scope): bool
    {
        $type = $scope->getType($receiver);

        foreach (self::BUILDER_CLASSES as $class) {
            if ((new ObjectType($class))->isSuperTypeOf($type)->yes()) {
                return true;
            }
        }

        return false;
    }

    private function isInCheckedNamespace(Scope $scope): bool
    {
        return $this->namespaceStartsWithAny($scope, $this->namespaces)
            && ! $this->namespaceStartsWithAny($scope, $this->excludeNamespaces);
    }
}
