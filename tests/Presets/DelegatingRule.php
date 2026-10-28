<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Presets;

use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * Runs several rules as one, so a preset that spans method, static and `new`
 * calls is tested against a single fixture, ignore comments included.
 *
 * @implements Rule<Node>
 */
final readonly class DelegatingRule implements Rule
{
    /**
     * @param  list<Rule<Node>>  $rules
     */
    public function __construct(
        private array $rules,
    ) {}

    #[Override]
    public function getNodeType(): string
    {
        return Node::class;
    }

    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->rules as $rule) {
            $nodeType = $rule->getNodeType();

            if ($node instanceof $nodeType) {
                array_push($errors, ...$rule->processNode($node, $scope));
            }
        }

        return $errors;
    }
}
