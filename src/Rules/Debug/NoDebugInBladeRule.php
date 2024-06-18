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
class NoDebugInBladeRule extends BaseNoDebugRule implements Rule
{
    protected string $message = 'No debug directives should be present in %s files.';

    public function __construct()
    {
        $this->haystack = [
            ...array_map(static fn (string $name) => "@$name", $this->haystack),
            '@ray',
        ];
    }

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
        if (! $this->hasDisallowedStatements($text)) {
            return [];
        }

        if ($message = sprintf($this->message, 'blade')) {
            return [
                RuleErrorBuilder::message($message)
                    ->identifier('hihaho.debug.no-debug-in-blade')
                    ->build(),
            ];
        }

        return [];
    }
}
