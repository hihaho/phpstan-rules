<?php declare(strict_types=1);

namespace Rules\Routing\SlashInUrl;

use Hihaho\PhpstanRules\Rules\NoInvadeInAppCode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoInvadeInAppCode>
 */
class NoInvadeInAppCodeTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoInvadeInAppCode();
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeInAppNamespace.php'], [
            [
                'Usage of method `invade` is not allowed in the App namespace.',
                12,
            ],
        ]);

        $this->analyse([__DIR__ . '/stubs/InvadeTestFake.php'], []);

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
}
