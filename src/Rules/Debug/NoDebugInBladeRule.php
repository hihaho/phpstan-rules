<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\InlineHTML;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @extends BaseNoDebugRule<\PHPStan\Node\FileNode>
 */
class NoDebugInBladeRule extends BaseNoDebugRule
{
    protected string $message = 'No debug directives should be present in %s files.';

    public function __construct()
    {
        $this->debugStatements = array_map(static fn (string $name): string => "@$name", $this->debugStatements);
    }

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @param FileNode $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Disabled, too performance heavy compared to looseness of check
        return [];
//
//        if (! Str::endsWith($scope->getFileDescription(), '.blade.php')) {
//            return [];
//        }
//
//        foreach ($node->getNodes() as $childNode) {
//            if (! $childNode instanceof InlineHTML) {
//                continue;
//            }
//
//            $containsDisallowedStatement = str($childNode->value)
//                ->stripTags()
//                ->replace(PHP_EOL, '')
//                ->trim()
//                ->contains($this->debugStatements);
//
//            if ($containsDisallowedStatement) {
//                return [
//                    RuleErrorBuilder::message(sprintf($this->message, 'Blade'))
//                        ->identifier('hihaho.debug.noDebugInBlade')
//                        ->build(),
//                ];
//            }
//        }
//
//        return [];
    }
}
