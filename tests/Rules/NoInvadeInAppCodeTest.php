<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules;

use Hihaho\PhpstanRules\Rules\NoInvadeInAppCode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoInvadeInAppCode>
 */
final class NoInvadeInAppCodeTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoInvadeInAppCode();
    }

    public function testFlagsInvadeInAppNamespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeInAppNamespace.php'], [
            [
                'Usage of method `invade` is not allowed in the App namespace.',
                12,
            ],
        ]);
    }

    public function testIgnoresInvadeInTestFakes(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeTestFake.php'], []);
    }

    public function testFlagsLivewireInvade(): void
    {
        $this->analyse([__DIR__ . '/stubs/UseSpatieInvadeInsteadOfLivewire.php'], [
            [
                'Usage of `\Livewire\invade` is disallowed, please use the global `invade` from spatie/invade.',
                15,
            ],
        ]);
    }

    public function testShouldNotMatchNamespacesStartingWithApp(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeInApplicationNamespace.php'], []);
    }

    public function testShouldFlagInvadeInAppSubNamespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeInAppSubNamespace.php'], [
            [
                'Usage of method `invade` is not allowed in the App namespace.',
                12,
            ],
        ]);
    }

    public function testShouldNotFlagInvadeInGlobalNamespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeInGlobalNamespace.php'], []);
    }

    public function testShouldNotFlagDynamicFunctionCall(): void
    {
        // Branch: `$node->name` is not `Node\Name` (dynamic call via variable).
        $this->analyse([__DIR__ . '/stubs/DynamicInvadeCallInAppNamespace.php'], []);
    }

    public function testShouldFlagLivewireInvadeRegardlessOfNamespace(): void
    {
        // The Livewire branch has no namespace guard — Vendor code calling
        // \Livewire\invade must still be flagged.
        $this->analyse([__DIR__ . '/stubs/LivewireInvadeInVendorNamespace.php'], [
            [
                'Usage of `\Livewire\invade` is disallowed, please use the global `invade` from spatie/invade.',
                14,
            ],
        ]);
    }

    public function testIdentifierForInvadeInAppCode(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/InvadeInAppNamespace.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.generic.noInvadeInAppCode', $error->getIdentifier());
        }
    }

    public function testIdentifierForLivewireInvade(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/UseSpatieInvadeInsteadOfLivewire.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.generic.disallowedUsageOfLivewireInvade', $error->getIdentifier());
        }
    }
}
