<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Collectors;

use Hihaho\PhpstanRules\Traits\DetectsPositionalFlagArgument;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Collects positional bool/null flag-argument sites into manifest records for
 * the sister rector rule (NamedArgumentFromManifestRector), which names them in
 * a consumer's larastan-equipped run. Reuses the same detection core as the
 * error rules — one implementation, two outputs (CI gate + manifest).
 *
 * @implements Collector<CallLike, array{pos: int, line: int, method: string, argIndex: int, paramName: string, value: string}>
 */
final readonly class FlagArgumentManifestCollector implements Collector
{
    use DetectsPositionalFlagArgument;

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private array $firstPartyNamespaces,
    ) {}

    #[Override]
    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @param  CallLike  $node
     * @return array{pos: int, line: int, method: string, argIndex: int, paramName: string, value: string}|null
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): ?array
    {
        $site = match (true) {
            $node instanceof MethodCall => $this->flagSiteForMethodCall($node, $scope, $this->firstPartyNamespaces),
            $node instanceof NullsafeMethodCall => $this->flagSiteForNullsafeMethodCall($node, $scope, $this->firstPartyNamespaces),
            $node instanceof StaticCall => $this->flagSiteForStaticCall($node, $scope, $this->reflectionProvider, $this->firstPartyNamespaces),
            $node instanceof New_ => $this->flagSiteForNew($node, $scope, $this->reflectionProvider, $this->firstPartyNamespaces),
            default => null,
        };

        if ($site === null) {
            return null;
        }

        // `pos` is the node's start byte offset — a stable per-call-site id used
        // only to dedup the writer's records. PHPStan visits a nullsafe call in
        // two scopes (same node → same pos → one record), while two distinct
        // same-line calls have different positions and are both kept.
        return [
            'pos' => $node->getStartFilePos(),
            'line' => $node->getStartLine(),
            'method' => $site['method'],
            'argIndex' => $site['argIndex'],
            'paramName' => $site['paramName'],
            'value' => $site['value'],
        ];
    }
}
