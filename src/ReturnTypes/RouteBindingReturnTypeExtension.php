<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\ReturnTypes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Parser\ParserErrorsException;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Types `$this->route('x')` / `$request->route('x')` (Illuminate\Http\Request::route()) as the model
 * bound to route parameter `x` in the application's route-service providers.
 *
 * Laravel types `route()` as `object|string|null`, so every `$model = $request->route('video_id')`
 * needs an `assert($model instanceof Video)` to recover the concrete type — and the parameter name
 * does not reveal the model (`video_id` → Video, `container` → VideoContainer). This extension reads
 * the actual bindings (`Route::model('x', M::class)` and `Route::bind('x', fn (): M => …)`) from the
 * configured provider classes and returns the bound model type, so the assert becomes unnecessary
 * while a wrong model assignment still fails.
 *
 * The binding map is parsed once from each provider's source — via the same name-resolving analysis
 * parser the package's other reflection uses, so class names in the providers resolve to their FQCN —
 * and memoized for the analysis run. It is configured per project through the `routeBindingProviders`
 * parameter; with none configured it resolves nothing and the default `object|string|null` stands.
 *
 * Scope and soundness:
 *  - Only a single constant-string argument is narrowed. `route()` with no argument (returns the Route
 *    object), a dynamic name, or an unknown parameter is left at its default type.
 *  - Only the instance method is covered (`$this->route()` / `$request->route()`); the `Request::`
 *    facade's static form is out of scope.
 *  - `Route::bind()` closures without a return-type hint, and `Route::model()` bindings that pass a
 *    missing-model callback (whose result can replace the model), are skipped.
 *  - Discovery is best-effort static parsing of the provider source: any `Route::model()`/`Route::bind()`
 *    in the file declaring `boot()` is treated as registered, so a binding behind an environment guard
 *    or in dead code is still picked up. Keep route bindings on the unconditional boot path.
 *  - A bound parameter is typed as the non-null model — matching the intent of the `assert()` calls
 *    it replaces, which also assumed the parameter was present. This over-claims by dropping `null`
 *    whenever the current route does not carry the parameter — an optional `{param?}` segment, or
 *    shared middleware/helpers reached from routes that lack it. That is an accepted tradeoff (the
 *    feature exists to remove those asserts); apply it to code reached only via routes that define
 *    the parameter, and keep an explicit null check where a request may legitimately lack it.
 */
final class RouteBindingReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    /**
     * Lazily-built, memoized map of route-parameter name => bound model type. Null until built.
     *
     * @var array<string, Type>|null
     */
    private ?array $bindings = null;

    /**
     * @param  list<string>  $routeBindingProviders  Provider class names whose source is parsed for bindings.
     */
    public function __construct(
        private readonly array $routeBindingProviders,
        private readonly Parser $parser,
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    #[Override]
    public function getClass(): string
    {
        return Request::class;
    }

    #[Override]
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'route';
    }

    #[Override]
    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $args = $methodCall->getArgs();

        // Narrow only the single-argument form. With a default (`route('x', $default)`) Laravel
        // returns that default when the parameter is missing, so the bound model is not guaranteed.
        if (! isset($args[0]) || isset($args[1])) {
            return null;
        }

        $parameterNames = $scope->getType($args[0]->value)->getConstantStrings();

        if (count($parameterNames) !== 1) {
            return null;
        }

        return $this->bindings($scope)[$parameterNames[0]->getValue()] ?? null;
    }

    /**
     * @return array<string, Type>
     */
    private function bindings(Scope $scope): array
    {
        if ($this->bindings !== null) {
            return $this->bindings;
        }

        $bindings = [];

        foreach ($this->routeBindingProviders as $provider) {
            foreach ($this->bindingsFromProvider($provider, $scope) as $parameter => $type) {
                $bindings[$parameter] = $type;
            }
        }

        return $this->bindings = $bindings;
    }

    /**
     * @return array<string, Type>
     */
    private function bindingsFromProvider(string $provider, Scope $scope): array
    {
        if (! $this->reflectionProvider->hasClass($provider)) {
            return [];
        }

        $classReflection = $this->reflectionProvider->getClass($provider);

        // Bindings live in boot(), which may be inherited from a shared base provider — parse the
        // file that actually declares it, not the configured subclass's file (which can be empty).
        $declaringClass = $classReflection->hasMethod('boot')
            ? $classReflection->getMethod('boot', $scope)->getDeclaringClass()
            : $classReflection;

        $file = $declaringClass->getFileName();

        if ($file === null) {
            return [];
        }

        try {
            $statements = $this->parser->parseFile($file);
        } catch (ParserErrorsException) {
            return [];
        }

        $bindings = [];

        foreach ((new NodeFinder())->findInstanceOf($statements, StaticCall::class) as $call) {
            $binding = $this->bindingFromCall($call);

            if ($binding === null) {
                continue;
            }

            [$parameter, $type] = $binding;
            $bindings[$parameter] = $type;
        }

        return $bindings;
    }

    /**
     * @return array{string, Type}|null
     */
    private function bindingFromCall(StaticCall $call): ?array
    {
        if (! $call->class instanceof Name || $call->class->toString() !== Route::class) {
            return null;
        }

        if (! $call->name instanceof Identifier) {
            return null;
        }

        $args = $call->getArgs();
        $parameterArgument = $args[0]->value ?? null;

        if (! $parameterArgument instanceof String_) {
            return null;
        }

        $type = match ($call->name->toLowerString()) {
            // A third "missing model" callback can return something other than the model, so a
            // binding that supplies one is skipped rather than narrowed unsoundly.
            'model' => isset($args[2]) ? null : $this->modelBindingType($args[1]->value ?? null),
            'bind' => $this->closureBindingType($args[1]->value ?? null),
            default => null,
        };

        if (! $type instanceof Type) {
            return null;
        }

        return [$parameterArgument->value, $type];
    }

    private function modelBindingType(?Node $argument): ?Type
    {
        if (! $argument instanceof ClassConstFetch || ! $argument->class instanceof Name) {
            return null;
        }

        if (! $argument->name instanceof Identifier || $argument->name->toLowerString() !== 'class') {
            return null;
        }

        return new ObjectType($argument->class->toString());
    }

    private function closureBindingType(?Node $argument): ?Type
    {
        if (! $argument instanceof Closure && ! $argument instanceof ArrowFunction) {
            return null;
        }

        $returnType = $argument->getReturnType();

        if ($returnType instanceof NullableType) {
            $returnType = $returnType->type;
        }

        if (! $returnType instanceof Name) {
            return null;
        }

        return new ObjectType($returnType->toString());
    }
}
