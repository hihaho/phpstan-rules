<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Html;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Laravel\Nova\Fields\Field;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Judges what a closure marked by HtmlFieldClosureVisitor returns. Nova prints
 * an `->asHtml()` field's value without escaping it, so the closure's return
 * value goes into the page as raw HTML.
 */
final readonly class HtmlFieldReturnSink
{
    use ChecksNamespace;

    private const string NOVA_FIELD = Field::class;

    /**
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        private array $namespaces,
        private array $excludeNamespaces,
        private HtmlSafetyJudge $judge,
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function isSink(Expr $closure, Scope $scope): bool
    {
        $fieldClass = $closure->getAttribute(HtmlFieldClosureVisitor::ATTRIBUTE);

        if (! is_string($fieldClass) || ! $this->namespaceStartsWithAny($scope, $this->namespaces) || $this->namespaceStartsWithAny($scope, $this->excludeNamespaces)) {
            return false;
        }

        return $this->reflectionProvider->hasClass($fieldClass)
            && $this->reflectionProvider->getClass($fieldClass)->is(self::NOVA_FIELD);
    }

    public function returnError(Expr $returned, Scope $scope, int $line): ?IdentifierRuleError
    {
        if ($this->judge->isSafe($returned, $scope)) {
            return null;
        }

        return RuleErrorBuilder::message("This asHtml() field renders the closure's return value without escaping it, and the value can contain HTML.")
            ->identifier('hihaho.html.unescapedHtmlField')
            ->tip('Escape user values with e(), or build the markup with spatie/laravel-html and ->text(). If it is trusted HTML, add @phpstan-ignore hihaho.html.unescapedHtmlField (reason).')
            ->line($line)
            ->build();
    }
}
