<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Conventions;

use Hihaho\PhpstanRules\Collectors\FlagArgumentManifestCollector;
use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Writes the named-argument manifest from the data gathered by
 * FlagArgumentManifestCollector. Runs once in the main process after analysis
 * (the CollectedDataNode is the single post-analysis node), emits no errors —
 * its only job is the JSON side artifact the sister rector rule consumes.
 *
 * @implements Rule<CollectedDataNode>
 */
final readonly class WriteNamedArgumentManifestRule implements Rule
{
    public function __construct(private string $outputPath) {}

    #[Override]
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @param  CollectedDataNode  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $records = [];

        foreach ($node->get(FlagArgumentManifestCollector::class) as $file => $fileRecords) {
            $relativeFile = $this->toRootRelative($file);

            foreach ($fileRecords as $record) {
                $normalized = $this->normalizeRecord($record);

                if ($normalized === null) {
                    continue;
                }

                $records[] = ['file' => $relativeFile] + $normalized;
            }
        }

        usort(
            $records,
            static fn (array $a, array $b): int => [$a['file'], $a['line'], $a['argIndex']] <=> [$b['file'], $b['line'], $b['argIndex']],
        );

        file_put_contents($this->outputPath, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        return [];
    }

    /**
     * Validates a deserialized collected record (collector data crosses the
     * worker→main boundary as plain arrays, so the type is not statically known).
     *
     * @return array{line: int, method: string, argIndex: int, paramName: string, value: string}|null
     */
    private function normalizeRecord(mixed $record): ?array
    {
        if (! is_array($record)
            || ! isset($record['line'], $record['method'], $record['argIndex'], $record['paramName'], $record['value'])
            || ! is_int($record['line'])
            || ! is_string($record['method'])
            || ! is_int($record['argIndex'])
            || ! is_string($record['paramName'])
            || ! is_string($record['value'])
        ) {
            return null;
        }

        return [
            'line' => $record['line'],
            'method' => $record['method'],
            'argIndex' => $record['argIndex'],
            'paramName' => $record['paramName'],
            'value' => $record['value'],
        ];
    }

    private function toRootRelative(string $file): string
    {
        $normalized = str_replace('\\', '/', $file);
        $root = getcwd();

        if ($root === false) {
            return $normalized;
        }

        $root = str_replace('\\', '/', $root);

        return str_starts_with($normalized, $root . '/')
            ? substr($normalized, strlen($root) + 1)
            : $normalized;
    }
}
