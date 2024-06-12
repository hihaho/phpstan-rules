<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\InlineHTML;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * @implements \PHPStan\Rules\Rule<\PHPStan\Node\FileNode>
 */
class NoDebugInBladeRule implements Rule
{
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @param FileNode $node
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! Str::endsWith($scope->getFileDescription(), '.blade.php')) {
            return [];
        }

        $text = array_map(static fn (InlineHTML $node): string => $node->value, $node->getNodes());
        if (self::hasDisallowedStatements(...$text)) {
            return [
                RuleErrorBuilder::message('No debug directives should be present in blade files.')->build(),
            ];
        }

        return [];
    }

    private static function hasDisallowedStatements(string $text): bool
    {
        return match (true) {
            str_contains($text, '@dump') => true,
            str_contains($text, '@dd') => true,
            str_contains($text, '@ray') => true,
            default => false,
        };
    }
}
