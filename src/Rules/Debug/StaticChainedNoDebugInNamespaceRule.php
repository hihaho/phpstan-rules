<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Illuminate\Support\Facades\Facade;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;

/**
 * @extends BaseNoDebugRule<StaticCall>
 */
final readonly class StaticChainedNoDebugInNamespaceRule extends BaseNoDebugRule
{
    private ?ClassReflection $facadeReflection;

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
        $this->facadeReflection = $reflectionProvider->hasClass(Facade::class)
            ? $reflectionProvider->getClass(Facade::class)
            : null;
    }

    #[Override]
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param  StaticCall  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $error = $this->staticDebugError($node, $node->name->name, $scope, $this->reflectionProvider, $this->facadeReflection);

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
