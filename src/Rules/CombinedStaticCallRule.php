<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Rules\Debug\BaseNoDebugRule;
use Hihaho\PhpstanRules\Traits\DetectsFacadeAlias;
use Hihaho\PhpstanRules\Traits\DetectsPositionalFlagArgument;
use Hihaho\PhpstanRules\Traits\DetectsUnsafeRequestFacade;
use Illuminate\Support\Facades\Facade;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;

/**
 * Combined rule that handles all StaticCall checks in a single PHPStan dispatch,
 * eliminating the overhead of dispatching to three separate rules for every static call.
 *
 * Merges: OnlyAllowFacadeAliasInBlade + StaticChainedNoDebugInNamespaceRule + NoUnsafeRequestFacadeRule
 *
 * @extends BaseNoDebugRule<StaticCall>
 */
final readonly class CombinedStaticCallRule extends BaseNoDebugRule
{
    use DetectsFacadeAlias;
    use DetectsPositionalFlagArgument;
    use DetectsUnsafeRequestFacade;

    private ?ClassReflection $facadeReflection;

    /** @var array<string, true> */
    private array $unsafeMethodsLookup;

    /**
     * @param  list<string>  $unsafeMethods
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     * @param  list<string>  $firstPartyNamespaces
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        array $unsafeMethods,
        private array $namespaces,
        private array $excludeNamespaces,
        private array $firstPartyNamespaces,
    ) {
        $this->facadeReflection = $reflectionProvider->hasClass(Facade::class)
            ? $reflectionProvider->getClass(Facade::class)
            : null;

        $this->unsafeMethodsLookup = array_fill_keys(array_map(strtolower(...), $unsafeMethods), true);
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
        if (! $node->class instanceof Name) {
            return [];
        }

        $errors = [];

        $facadeError = $this->facadeAliasError($node->class, $scope);
        if ($facadeError instanceof IdentifierRuleError) {
            $errors[] = $facadeError;
        }

        $flagError = $this->positionalFlagErrorForStaticCall($node, $scope, $this->reflectionProvider, $this->firstPartyNamespaces);
        if ($flagError instanceof IdentifierRuleError) {
            $errors[] = $flagError;
        }

        if (! $node->name instanceof Identifier) {
            return $errors;
        }

        $methodName = $node->name->name;

        $debugError = $this->staticDebugError($node, $methodName, $scope, $this->reflectionProvider, $this->facadeReflection);
        if ($debugError instanceof IdentifierRuleError) {
            $errors[] = $debugError;
        }

        $requestError = $this->unsafeRequestFacadeError($node->class, $methodName, $scope, $this->unsafeMethodsLookup, $this->namespaces, $this->excludeNamespaces);
        if ($requestError instanceof IdentifierRuleError) {
            $errors[] = $requestError;
        }

        return $errors;
    }
}
