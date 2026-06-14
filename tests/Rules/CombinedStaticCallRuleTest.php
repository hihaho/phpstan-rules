<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules;

use App\Facades\Custom;
use Hihaho\PhpstanRules\Rules\CombinedStaticCallRule;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers the registered CombinedStaticCallRule by mirroring the cases of its
 * four merged twins (facade-alias, static-chained-debug, unsafe-request-facade,
 * positional-flag) against the same stub fixtures.
 *
 * The facade-alias cases require Laravel's lazy alias loader, replicated in
 * setUp() from OnlyAllowFacadeAliasInBladeTest — without it the short `Route` /
 * `Custom` names never resolve to a Facade subclass and those cases would not
 * flag.
 *
 * @extends RuleTestCase<CombinedStaticCallRule>
 */
final class CombinedStaticCallRuleTest extends RuleTestCase
{
    private const string FACADE_REQ_PATTERN = 'Reading unvalidated request data via ' . Request::class . '::%s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().';

    private const string FACADE_REQ_TIP = 'Inject a FormRequest (or Request typehint) and consume via $request->validated() / $request->safe() instead of the Request facade.';

    /**
     * @var array<string, class-string<Facade>>
     */
    private array $aliases = [
        \Route::class => Route::class, // @phpstan-ignore-line
        \Custom::class => Custom::class, // @phpstan-ignore-line
    ];

    protected function setUp(): void
    {
        parent::setUp();

        /**
         * Register Laravel's alias loader pattern — a lazy SPL autoloader that
         * resolves the short facade name only on first access, mirroring
         * Laravel's real-world behaviour. Only `Route` and `Custom` are aliased,
         * so this is harmless to the debug / request-facade / positional cases.
         *
         * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Foundation/AliasLoader.php
         */
        spl_autoload_register($this->autoload(...), throw: true, prepend: true);
    }

    public function autoload(string $alias): void
    {
        if (isset($this->aliases[$alias])) {
            class_alias($this->aliases[$alias], $alias);
        }
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new CombinedStaticCallRule(
            reflectionProvider: self::createReflectionProvider(),
            unsafeMethods: [
                'input', 'all', 'get', 'query', 'post', 'only', 'except', 'collect',
                'string', 'str', 'integer', 'boolean', 'float', 'json', 'keys',
                'fluent', 'array', 'date', 'enum', 'enums', 'file', 'allFiles',
            ],
            namespaces: ['App'],
            excludeNamespaces: ['App\\Providers', 'App\\Http\\Responses'],
            firstPartyNamespaces: ['App', 'Database\\Factories', 'Tests'],
        );
    }

    // ----- facade-alias concern (mirrors OnlyAllowFacadeAliasInBladeTest) -----

    #[Test]
    public function blade_files_are_ignored(): void
    {
        $this->analyse([__DIR__ . '/stubs/facade-alias-in-view.blade.php'], []);
    }

    #[Test]
    public function fully_qualified_facade_usage_is_allowed(): void
    {
        $this->analyse([__DIR__ . '/stubs/FullFacadeNamespaceInClass.php'], []);
    }

    #[Test]
    public function facade_alias_in_class_is_flagged(): void
    {
        $this->analyse([__DIR__ . '/stubs/FacadeAliasInClass.php'], [
            [
                'Disallowed usage of `Route` facade alias, use `Illuminate\Support\Facades\Route`. A facade alias can only be used in Blade.',
                12,
            ],
            [
                'Disallowed usage of `Custom` facade alias, use `App\Facades\Custom`. A facade alias can only be used in Blade.',
                14,
            ],
        ]);
    }

    #[Test]
    public function should_not_crash_on_non_existent_class(): void
    {
        $this->analyse([__DIR__ . '/stubs/NonExistentClassStaticCall.php'], []);
    }

    #[Test]
    public function should_not_flag_non_facade_alias(): void
    {
        $this->analyse([__DIR__ . '/stubs/NonFacadeAliasStaticCall.php'], []);
    }

    #[Test]
    public function should_not_flag_dynamic_static_class(): void
    {
        $this->analyse([__DIR__ . '/stubs/DynamicStaticCallInAppNamespace.php'], []);
    }

    #[Test]
    public function facade_alias_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/FacadeAliasInClass.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.generic.onlyAllowFacadeAliasInBlade', $error->getIdentifier());
        }
    }

    // ----- static-chained-debug concern (mirrors NoStaticChainedDebugInNamespaceTest) -----

    #[Test]
    public function should_not_contain_static_called_debug_statements_in_app_namespace(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/StaticChainedDebugInAppNamespaceStub.php'], [
            ['No statically called debug statements should be present in the App namespace.', 11],
            ['No statically called debug statements should be present in the App namespace.', 12],
        ]);
    }

    #[Test]
    public function should_not_contain_static_called_debug_statements_in_tests_namespace(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/StaticChainedDebugInTestNamespaceStub.php'], [
            ['No statically called debug statements should be present in the Tests namespace.', 11],
            ['No statically called debug statements should be present in the Tests namespace.', 12],
        ]);
    }

    #[Test]
    public function should_not_flag_static_called_debug_statements_outside_app_and_tests_namespaces(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/StaticChainedDebugInVendorNamespaceStub.php'], []);
    }

    #[Test]
    public function should_not_flag_user_static_dump_method(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/UserStaticDumpInAppNamespaceStub.php'], []);
    }

    #[Test]
    public function should_flag_facade_dump_without_method_annotation(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/UnannotatedFacadeStaticDumpStub.php'], [
            ['No statically called debug statements should be present in the App namespace.', 15],
            ['No statically called debug statements should be present in the App namespace.', 16],
        ]);
    }

    #[Test]
    public function should_flag_user_defined_facade_subclass_static_dump(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/UserFacadeStaticDumpInAppNamespaceStub.php'], [
            ['No statically called debug statements should be present in the App namespace.', 27],
            ['No statically called debug statements should be present in the App namespace.', 28],
        ]);
    }

    #[Test]
    public function should_not_flag_dynamic_static_method_name(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/DynamicStaticCallInAppNamespaceStub.php'], []);
    }

    #[Test]
    public function static_debug_uses_correct_identifier_in_app(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Debug/stubs/StaticChainedDebugInAppNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noStaticChainedDebugInApp', $error->getIdentifier());
        }
    }

    #[Test]
    public function static_debug_uses_correct_identifier_in_tests(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Debug/stubs/StaticChainedDebugInTestNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noStaticChainedDebugInTests', $error->getIdentifier());
        }
    }

    #[Test]
    public function should_flag_laravel_debug_methods_called_statically(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/AllStaticChainedDebugInAppNamespaceStub.php'], [
            ['No statically called debug statements should be present in the App namespace.', 11],
            ['No statically called debug statements should be present in the App namespace.', 16],
        ]);
    }

    // ----- unsafe-request-facade concern (mirrors NoUnsafeRequestFacadeRuleTest) -----

    #[Test]
    public function flags_unsafe_static_calls_on_facade_via_import(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestFacadeCallStub.php'], [
            [sprintf(self::FACADE_REQ_PATTERN, 'boolean'), 15, self::FACADE_REQ_TIP],
            [sprintf(self::FACADE_REQ_PATTERN, 'input'), 16, self::FACADE_REQ_TIP],
            [sprintf(self::FACADE_REQ_PATTERN, 'all'), 17, self::FACADE_REQ_TIP],
        ]);
    }

    #[Test]
    public function flags_fully_qualified_facade_calls(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestFacadeFullyQualifiedStub.php'], [
            [sprintf(self::FACADE_REQ_PATTERN, 'boolean'), 15, self::FACADE_REQ_TIP],
        ]);
    }

    #[Test]
    public function flags_facade_calls_via_aliased_import(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestFacadeAliasedImportStub.php'], [
            [sprintf(self::FACADE_REQ_PATTERN, 'boolean'), 15, self::FACADE_REQ_TIP],
            [sprintf(self::FACADE_REQ_PATTERN, 'all'), 16, self::FACADE_REQ_TIP],
        ]);
    }

    #[Test]
    public function does_not_flag_non_unsafe_facade_methods(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestFacadeSafeMethodStub.php'], []);
    }

    #[Test]
    public function does_not_flag_facade_calls_outside_configured_namespace(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestFacadeOutsideNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_facade_calls_inside_excluded_namespace(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/ProvidersNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_illuminate_http_request_static_calls(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/HttpRequestStaticCallStub.php'], []);
    }

    #[Test]
    public function flags_file_upload_facade_methods(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/FileUploadFacadeStub.php'], [
            [sprintf(self::FACADE_REQ_PATTERN, 'file'), 15, self::FACADE_REQ_TIP],
            [sprintf(self::FACADE_REQ_PATTERN, 'allFiles'), 16, self::FACADE_REQ_TIP],
        ]);
    }

    #[Test]
    public function request_facade_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Validation/stubs/RequestFacadeCallStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.validation.noUnsafeRequestFacade', $error->getIdentifier());
        }
    }

    // ----- positional-flag concern (mirrors PositionalFlagArgumentStaticCallRuleTest) -----

    #[Test]
    public function flags_a_trailing_flag_on_a_first_party_static_call_only(): void
    {
        $this->analyse([__DIR__ . '/Conventions/stubs/StaticFlagCallStub.php'], [
            [
                'Pass a named argument (on: ...) for the bool/null flag — it is opaque positionally.',
                23,
                'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).',
            ],
        ]);
    }
}
