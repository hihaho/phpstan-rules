<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Exceptions;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Override;
use PhpParser\Node;
use PhpParser\Node\Stmt\Catch_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;

/**
 * A catch-all that neither rethrows, reports, nor reads what it caught turns
 * every failure in the try block into silent success: the job completes, the
 * request returns 200, and nothing reaches the error tracker.
 *
 * Only `Throwable` and `Exception` are reported by default. A narrow catch names
 * the failure it expects, which is usually a deliberate decision.
 *
 * Inert until a project lists the namespaces to check.
 *
 * @implements Rule<Catch_>
 * @see SwallowedExceptionRuleTest
 */
final readonly class SwallowedExceptionRule implements Rule
{
    use ChecksNamespace;

    private const array BROAD_TYPES = [
        'throwable' => true,
        'exception' => true,
    ];

    private const string BOOL_PROBE_PATTERN = '/^(check|can|is|has|supports|verify|declares|validate|should)(?![a-z])/';

    private const string NULLABLE_FETCH_PATTERN = '/^(try|parse|maybe|find|fetch|resolve)(?![a-z])/';

    /**
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        private array $namespaces,
        private array $excludeNamespaces,
        private bool $reportNarrowCatches,
        private CatchBodyInspector $inspector,
    ) {}

    #[Override]
    public function getNodeType(): string
    {
        return Catch_::class;
    }

    /**
     * @param  Catch_  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->namespaceStartsWithAny($scope, $this->namespaces) || $this->namespaceStartsWithAny($scope, $this->excludeNamespaces)) {
            return [];
        }

        $caughtType = $this->reportedType($node);

        if ($caughtType === null || $node->getAttribute(NestedCatchVisitor::ATTRIBUTE) === true) {
            return [];
        }

        if ($this->isExemptByEnclosingFunction($scope) || $this->inspector->handles($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message("Catching {$caughtType} without rethrowing, reporting or reading it turns every failure into silent success.")
                ->identifier('hihaho.exceptions.swallowedException')
                ->tip('Rethrow it, pass it to report(), or read the caught variable. If swallowing is deliberate, add @phpstan-ignore hihaho.exceptions.swallowedException (reason).')
                ->build(),
        ];
    }

    private function reportedType(Catch_ $node): ?string
    {
        foreach ($node->types as $type) {
            if ($this->reportNarrowCatches || isset(self::BROAD_TYPES[$type->toLowerString()])) {
                return $type->toString();
            }
        }

        return null;
    }

    /**
     * A bool probe (`isReachable(): bool`) and a nullable fetch
     * (`tryParse(): ?Carbon`) put the failure in their return value: false or
     * null *is* the report, and the caller is written to expect it.
     */
    private function isExemptByEnclosingFunction(Scope $scope): bool
    {
        $function = $scope->getFunction();

        if (! $function instanceof PhpFunctionFromParserNodeReflection) {
            return false;
        }

        $name = $function->getName();
        $returnType = $function->getReturnType();

        if ($returnType->isBoolean()->yes() && preg_match(self::BOOL_PROBE_PATTERN, $name) === 1) {
            return true;
        }

        return TypeCombinator::containsNull($returnType)
            && ! $returnType->isNull()->yes()
            && preg_match(self::NULLABLE_FETCH_PATTERN, $name) === 1;
    }
}
