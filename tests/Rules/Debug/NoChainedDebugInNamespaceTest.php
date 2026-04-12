<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Debug;

use Hihaho\PhpstanRules\Rules\Debug\ChainedNoDebugInNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<ChainedNoDebugInNamespaceRule>
 */
final class NoChainedDebugInNamespaceTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ChainedNoDebugInNamespaceRule();
    }

    #[Test]
    public function should_not_contain_chained_debug_statements_in_app_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/ChainedDebugInAppNamespaceStub.php'], [
            ['No chained debug statements should be present in the App namespace.', 9],
            ['No chained debug statements should be present in the App namespace.', 10],
        ]);
    }

    #[Test]
    public function should_not_contain_chained_debug_statements_in_tests_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/ChainedDebugInTestNamespaceStub.php'], [
            ['No chained debug statements should be present in the Tests namespace.', 9],
            ['No chained debug statements should be present in the Tests namespace.', 10],
        ]);
    }

    #[Test]
    public function should_not_flag_chained_debug_statements_outside_app_and_tests_namespaces(): void
    {
        $this->analyse([__DIR__ . '/stubs/ChainedDebugInVendorNamespaceStub.php'], []);
    }

    #[Test]
    public function should_not_flag_user_classes_with_their_own_dump_method(): void
    {
        // Narrowing guarantee: a ->dump() / ->dd() on a user class (not declared
        // by an Illuminate\* class/trait) must not be flagged. Regressing the
        // narrowing to name-only matching would break this test.
        $this->analyse([__DIR__ . '/stubs/CustomDumpableInAppNamespaceStub.php'], []);
    }

    #[Test]
    public function should_not_flag_when_receiver_type_is_unknown(): void
    {
        // Narrowing bail-out: if PHPStan cannot resolve the receiver to any
        // object class, the rule must skip to avoid false positives. Protects
        // the "no object reflections" branch in isDebugHelperMethodCall().
        $this->analyse([__DIR__ . '/stubs/UnknownReceiverChainedDumpStub.php'], []);
    }

    #[Test]
    public function should_not_flag_dynamic_method_name(): void
    {
        // Branch: `$node->name` is not `Node\Identifier` (dynamic method name).
        $this->analyse([__DIR__ . '/stubs/DynamicMethodCallInAppNamespaceStub.php'], []);
    }

    #[Test]
    public function should_flag_union_type_receiver_when_any_member_is_laravel(): void
    {
        // Exercises the `foreach ($classReflections as $classReflection)` loop:
        // $value is `Collection|UserValueObject`. Collection's dump() is
        // declared in Illuminate\*; UserValueObject's isn't. The rule must
        // still flag because at least one union member is a Laravel debug
        // helper.
        $this->analyse([__DIR__ . '/stubs/UnionReceiverChainedDumpStub.php'], [
            ['No chained debug statements should be present in the App namespace.', 29],
        ]);
    }

    #[Test]
    public function should_flag_all_method_debug_statements_declared_by_illuminate(): void
    {
        $this->analyse([__DIR__ . '/stubs/AllChainedDebugInAppNamespaceStub.php'], [
            ['No chained debug statements should be present in the App namespace.', 9],
            ['No chained debug statements should be present in the App namespace.', 14],
        ]);
    }

    #[Test]
    public function should_have_correct_error_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/ChainedDebugInAppNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noChainedDebugInApp', $error->getIdentifier());
        }
    }
}
