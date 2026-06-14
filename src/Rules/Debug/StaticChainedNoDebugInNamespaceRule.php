<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Hihaho\PhpstanRules\Traits\DetectsLaravelStaticDebugCall;
use Illuminate\Support\Facades\Facade;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @extends BaseNoDebugRule<StaticCall>
 */
final readonly class StaticChainedNoDebugInNamespaceRule extends BaseNoDebugRule
{
    use DetectsLaravelStaticDebugCall;

    private const string MESSAGE = 'No statically called debug statements should be present in the %s namespace.';

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

        $methodName = $node->name->name;

        if (! $this->isDebugMethod($methodName)) {
            return [];
        }

        $namespace = $this->matchDebugNamespace($scope);

        if ($namespace === null) {
            return [];
        }

        if (! $this->isLaravelStaticDebugCall($node, $scope, $methodName, $this->reflectionProvider, $this->facadeReflection)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(self::MESSAGE, $namespace))
                ->identifier("hihaho.debug.noStaticChainedDebugIn{$namespace}")
                ->build(),
        ];
    }
}
