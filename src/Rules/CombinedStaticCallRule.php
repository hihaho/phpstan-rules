<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Rules\Debug\BaseNoDebugRule;
use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Request;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionClass;
use ReflectionException;

/**
 * Combined rule that handles all StaticCall checks in a single PHPStan dispatch,
 * eliminating the overhead of dispatching to three separate rules for every static call.
 *
 * Merges: OnlyAllowFacadeAliasInBlade + StaticChainedNoDebugInNamespaceRule + NoUnsafeRequestFacadeRule
 *
 * @implements Rule<StaticCall>
 */
final readonly class CombinedStaticCallRule extends BaseNoDebugRule implements Rule
{
    use ChecksNamespace;

    private const string DEBUG_MESSAGE = 'No statically called debug statements should be present in the %s namespace.';

    private readonly ?ClassReflection $facadeReflection;

    private readonly string $requestFacadeClassLower;

    /** @var array<string, true> */
    private readonly array $unsafeMethodsLookup;

    /**
     * @param  list<string>  $unsafeMethods
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        array $unsafeMethods,
        private array $namespaces,
        private array $excludeNamespaces,
    ) {
        $this->facadeReflection = $reflectionProvider->hasClass(Facade::class)
            ? $reflectionProvider->getClass(Facade::class)
            : null;

        $this->requestFacadeClassLower = strtolower(Request::class);
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

        // --- OnlyAllowFacadeAliasInBlade ---
        if (! $node->class->isRelative()
            && ! $node->class->isSpecialClassName()
            && ! str_contains($node->class->name, '\\')
            && ! str_ends_with($scope->getFileDescription(), '.blade.php')
        ) {
            $facadeError = $this->checkFacadeAlias($node);

            if ($facadeError !== null) {
                $errors[] = $facadeError;
            }
        }

        if (! $node->name instanceof Identifier) {
            return $errors;
        }

        $methodName = $node->name->name;

        // --- StaticChainedNoDebugInNamespaceRule ---
        if ($this->isDebugMethod($methodName)) {
            if ($this->namespaceStartsWith($scope, 'App')) {
                if ($this->isLaravelStaticDebugCall($node, $scope, $methodName)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(self::DEBUG_MESSAGE, 'App'))
                        ->identifier('hihaho.debug.noStaticChainedDebugInApp')
                        ->build();
                }
            } elseif ($this->namespaceStartsWith($scope, 'Tests')) {
                if ($this->isLaravelStaticDebugCall($node, $scope, $methodName)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(self::DEBUG_MESSAGE, 'Tests'))
                        ->identifier('hihaho.debug.noStaticChainedDebugInTests')
                        ->build();
                }
            }
        }

        // --- NoUnsafeRequestFacadeRule ---
        if ($node->class->getLast() === 'Request'
            && strtolower($node->class->name) === $this->requestFacadeClassLower
            && isset($this->unsafeMethodsLookup[strtolower($methodName)])
            && $this->isInRequestNamespace($scope)
        ) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Reading unvalidated request data via %s::%s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
                Request::class,
                $methodName,
            ))
                ->identifier('hihaho.validation.noUnsafeRequestFacade')
                ->tip('Inject a FormRequest (or Request typehint) and consume via $request->validated() / $request->safe() instead of the Request facade.')
                ->build();
        }

        return $errors;
    }

    private function checkFacadeAlias(StaticCall $node): ?IdentifierRuleError
    {
        $className = $node->class->name;

        /** @var array<string, ReflectionClass<object>|null> $cache */
        static $cache = [];

        if (! array_key_exists($className, $cache)) {
            try {
                // Runtime reflection is required: facade aliases are registered
                // lazily by Laravel's AliasLoader (an SPL autoloader). PHPStan's
                // ReflectionProvider does not invoke runtime autoloaders, so a
                // static-discovery path would silently miss every real-world
                // facade alias. The try/catch handles non-existent short names.
                // @phpstan-ignore phpstanApi.runtimeReflection, argument.type
                $cache[$className] = new ReflectionClass($className);
            } catch (ReflectionException) {
                $cache[$className] = null;
            }
        }

        $reflectionClass = $cache[$className];

        if ($reflectionClass === null) {
            return null;
        }

        if ($reflectionClass->isSubclassOf(Facade::class)) {
            return RuleErrorBuilder::message(
                "Disallowed usage of `{$node->class->name}` facade alias, use `{$reflectionClass->getName()}`. A facade alias can only be used in Blade."
            )
                ->identifier('hihaho.generic.onlyAllowFacadeAliasInBlade')
                ->build();
        }

        return null;
    }

    private function isLaravelStaticDebugCall(StaticCall $node, Scope $scope, string $methodName): bool
    {
        if (! $node->class instanceof Name) {
            return false;
        }

        $className = $scope->resolveName($node->class);

        if (! $this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        if ($classReflection->hasMethod($methodName)) {
            $declaringClassName = $classReflection->getMethod($methodName, $scope)->getDeclaringClass()->getName();

            if (str_starts_with($declaringClassName, self::LARAVEL_NAMESPACE_PREFIX)) {
                return true;
            }
        }

        return $this->isFacadeSubclass($classReflection);
    }

    private function isFacadeSubclass(ClassReflection $classReflection): bool
    {
        if ($this->facadeReflection === null) {
            return false;
        }

        return $classReflection->isSubclassOfClass($this->facadeReflection);
    }

    private function isInRequestNamespace(Scope $scope): bool
    {
        return $this->namespaceStartsWithAny($scope, $this->namespaces)
            && ! $this->namespaceStartsWithAny($scope, $this->excludeNamespaces);
    }
}
