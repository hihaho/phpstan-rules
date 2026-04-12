<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Debug;

use Hihaho\PhpstanRules\Rules\Debug\StaticChainedNoDebugInNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<StaticChainedNoDebugInNamespaceRule>
 */
final class NoStaticChainedDebugInNamespaceTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new StaticChainedNoDebugInNamespaceRule($this->createReflectionProvider());
    }

    #[Test]
    public function should_not_contain_static_called_debug_statements_in_app_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/StaticChainedDebugInAppNamespaceStub.php'], [
            ['No statically called debug statements should be present in the App namespace.', 11],
            ['No statically called debug statements should be present in the App namespace.', 12],
        ]);
    }

    #[Test]
    public function should_not_contain_static_called_debug_statements_in_tests_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/StaticChainedDebugInTestNamespaceStub.php'], [
            ['No statically called debug statements should be present in the Tests namespace.', 11],
            ['No statically called debug statements should be present in the Tests namespace.', 12],
        ]);
    }

    #[Test]
    public function should_not_flag_static_called_debug_statements_outside_app_and_tests_namespaces(): void
    {
        $this->analyse([__DIR__ . '/stubs/StaticChainedDebugInVendorNamespaceStub.php'], []);
    }

    #[Test]
    public function should_not_flag_user_static_dump_method(): void
    {
        // Narrowing guarantee: a user class's static ::dump()/::dd() is not a
        // Laravel debug helper and must not be flagged.
        $this->analyse([__DIR__ . '/stubs/UserStaticDumpInAppNamespaceStub.php'], []);
    }

    #[Test]
    public function should_flag_facade_dump_without_method_annotation(): void
    {
        // Regression guard: facades that don't declare `dump`/`dd` via
        // `@method` annotations still proxy the call through
        // `Facade::__callStatic`. The Facade-subclass fallback in
        // isLaravelStaticDebugCall() catches these.
        $this->analyse([__DIR__ . '/stubs/UnannotatedFacadeStaticDumpStub.php'], [
            ['No statically called debug statements should be present in the App namespace.', 15],
            ['No statically called debug statements should be present in the App namespace.', 16],
        ]);
    }

    #[Test]
    public function should_flag_user_defined_facade_subclass_static_dump(): void
    {
        // The Facade-subclass fallback is intentionally broad: any subclass of
        // Illuminate\Support\Facades\Facade proxies via `__callStatic`, so a
        // user-defined facade like `App\Facades\MyFacade::dump()` still flags.
        // Pins the current behavior against a future "narrow fallback to
        // Illuminate\* facades only" regression.
        $this->analyse([__DIR__ . '/stubs/UserFacadeStaticDumpInAppNamespaceStub.php'], [
            ['No statically called debug statements should be present in the App namespace.', 27],
            ['No statically called debug statements should be present in the App namespace.', 28],
        ]);
    }

    #[Test]
    public function should_not_flag_dynamic_static_method_name(): void
    {
        // Branch: `$node->name` is not `Node\Identifier` (dynamic method name).
        $this->analyse([__DIR__ . '/stubs/DynamicStaticCallInAppNamespaceStub.php'], []);
    }

    #[Test]
    public function should_have_correct_error_identifier_in_app(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/StaticChainedDebugInAppNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noStaticChainedDebugInApp', $error->getIdentifier());
        }
    }

    #[Test]
    public function should_have_correct_error_identifier_in_tests(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/StaticChainedDebugInTestNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noStaticChainedDebugInTests', $error->getIdentifier());
        }
    }

    #[Test]
    public function should_flag_laravel_debug_methods_called_statically(): void
    {
        // Only real Laravel static debug methods (e.g. Facade::dump/dd proxying
        // to Dumpable/EnumeratesValues) should flag. Non-existent methods on
        // Laravel classes (e.g. Http::ddd()) are runtime errors and not the
        // responsibility of this rule to surface.
        $this->analyse([__DIR__ . '/stubs/AllStaticChainedDebugInAppNamespaceStub.php'], [
            ['No statically called debug statements should be present in the App namespace.', 11],
            ['No statically called debug statements should be present in the App namespace.', 16],
        ]);
    }
}
