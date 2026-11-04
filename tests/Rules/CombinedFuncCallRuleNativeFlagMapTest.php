<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules;

use Hihaho\PhpstanRules\Rules\CombinedFuncCallRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<CombinedFuncCallRule>
 */
final class CombinedFuncCallRuleNativeFlagMapTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        return new CombinedFuncCallRule(
            namespaces: ['App'],
            excludeNamespaces: [],
            reflectionProvider: self::createReflectionProvider(),
            nativeFunctionFlags: ['ARRAY_KEYS' => [1 => 'filter_value']],
        );
    }

    #[Test]
    public function checks_exactly_the_configured_functions_matching_their_name_in_any_case(): void
    {
        $this->analyse([__DIR__ . '/Conventions/stubs/NativeFlagCustomMapStub.php'], [
            [
                'Pass a named argument (filter_value: ...) for the bool/null flag — it is opaque positionally.',
                12,
                'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).',
            ],
        ]);
    }
}
