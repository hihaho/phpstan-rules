<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\ReturnTypes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Parser\ParserErrorsException;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Resolves Laravel implicit route-model bindings to the bound model type, by statically reading the
 * application's route files — no `route:list` manifest, no runtime boot.
 *
 * Laravel's `ImplicitRouteBinding` derives the model from the controller method's type-hint, matching
 * the route parameter to the method parameter by name or `Str::snake()` of it. This mirrors that 1:1:
 * for each `Route::<verb>('uri/{param}', Action)` in a configured route file, it resolves the action's
 * method, finds the parameter Laravel would bind to each route parameter, and keeps its type when it
 * is a Model. `route('param')` is then the union of those models across every route that declares it
 * (a call site can run under multiple routes, so the union is the sound static type).
 *
 * Best-effort and fail-safe: only `Route::<verb>(string, Action)` calls with a `Controller::class`
 * (invokable `__invoke`) or `[Controller::class, 'method']` action are read. Anything else — a closure
 * or string action, a group-level controller, `Route::resource`, a non-model or un-hinted parameter —
 * is skipped, leaving the default type. It never mis-types; it only narrows what it can prove.
 */
final class ImplicitRouteBindingResolver
{
    private const array ROUTE_VERBS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'any', 'match'];

    /**
     * Lazily-built, memoized map of route-parameter name => bound model type. Null until built.
     *
     * @var array<string, Type>|null
     */
    private ?array $bindings = null;

    /**
     * @param  list<string>  $routeFiles  Route file paths parsed for implicit (type-hint) bindings.
     */
    public function __construct(
        private readonly array $routeFiles,
        private readonly Parser $parser,
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    /**
     * @return array<string, Type>
     */
    public function bindings(Scope $scope): array
    {
        if ($this->bindings !== null) {
            return $this->bindings;
        }

        /** @var array<string, list<Type>> $collected */
        $collected = [];

        foreach ($this->routeFiles as $routeFile) {
            foreach ($this->routeCalls($routeFile) as $call) {
                $this->collectFromCall($call, $scope, $collected);
            }
        }

        $bindings = [];

        foreach ($collected as $parameter => $types) {
            $bindings[$parameter] = TypeCombinator::union(...$types);
        }

        return $this->bindings = $bindings;
    }

    /**
     * @return list<StaticCall>
     */
    private function routeCalls(string $routeFile): array
    {
        // A misconfigured or unreadable route-file path must not break analysis — fail safe.
        if (! is_file($routeFile) || ! is_readable($routeFile)) {
            return [];
        }

        try {
            $statements = $this->parser->parseFile($routeFile);
        } catch (ParserErrorsException) {
            return [];
        }

        // Simple direct parser does not resolve names, and is not the default analysis parser whose
        // facade static calls are unreliable under larastan — resolve names ourselves.
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $statements = $traverser->traverse($statements);

        return array_values((new NodeFinder())->findInstanceOf($statements, StaticCall::class));
    }

    /**
     * @param  array<string, list<Type>>  $collected
     */
    private function collectFromCall(StaticCall $call, Scope $scope, array &$collected): void
    {
        if (! $call->class instanceof Name || $call->class->toString() !== Route::class || ! $call->name instanceof Identifier) {
            return;
        }

        if (! in_array($call->name->toLowerString(), self::ROUTE_VERBS, true)) {
            return;
        }

        $args = $call->getArgs();

        // `match($methods, $uri, $action)` shifts the uri/action by one; every other verb is ($uri, $action).
        $offset = $call->name->toLowerString() === 'match' ? 1 : 0;
        $uri = $args[$offset]->value ?? null;
        $action = $args[$offset + 1]->value ?? null;

        if (! $uri instanceof String_ || $action === null) {
            return;
        }

        $method = $this->resolveActionMethod($action);

        if ($method === null) {
            return;
        }

        foreach ($this->routeParameters($uri->value) as $parameter) {
            $type = $this->boundModelType($method, $parameter, $scope);

            if ($type instanceof Type) {
                $collected[$parameter][] = $type;
            }
        }
    }

    /**
     * The action's [class, method]: a `Controller::class` is invokable (`__invoke`); a
     * `[Controller::class, 'method']` names the method. Closures and string actions are skipped.
     *
     * @return array{string, string}|null
     */
    private function resolveActionMethod(Node $action): ?array
    {
        if ($action instanceof ClassConstFetch) {
            return $action->class instanceof Name ? [$action->class->toString(), '__invoke'] : null;
        }

        if (! $action instanceof Array_ || count($action->items) !== 2) {
            return null;
        }

        $class = $action->items[0]->value;
        $method = $action->items[1]->value;

        if (! $class instanceof ClassConstFetch || ! $class->class instanceof Name || ! $method instanceof String_) {
            return null;
        }

        return [$class->class->toString(), $method->value];
    }

    /**
     * Route parameter names from a URI, dropping the optional marker and any binding field
     * (`{video}`, `{video?}`, `{video:slug}` all yield `video`).
     *
     * @return list<string>
     */
    private function routeParameters(string $uri): array
    {
        if (preg_match_all('/\{(\w+)/', $uri, $matches) === false) {
            return [];
        }

        return $matches[1];
    }

    /**
     * The bound model type for a route parameter: the type of the action-method parameter Laravel
     * would match to it (exact name, or `Str::snake()` of the method parameter name), when a Model.
     *
     * @param  array{string, string}  $method
     */
    private function boundModelType(array $method, string $routeParameter, Scope $scope): ?Type
    {
        [$class, $methodName] = $method;

        if (! $this->reflectionProvider->hasClass($class)) {
            return null;
        }

        $classReflection = $this->reflectionProvider->getClass($class);

        if (! $classReflection->hasMethod($methodName)) {
            return null;
        }

        $parameters = ParametersAcceptorSelector::selectFromArgs(
            $scope,
            [],
            $classReflection->getMethod($methodName, $scope)->getVariants(),
        )->getParameters();

        foreach ($parameters as $parameter) {
            if ($parameter->getName() !== $routeParameter && Str::snake($parameter->getName()) !== $routeParameter) {
                continue;
            }

            return (new ObjectType(Model::class))->isSuperTypeOf($parameter->getType())->yes()
                ? $parameter->getType()
                : null;
        }

        return null;
    }
}
