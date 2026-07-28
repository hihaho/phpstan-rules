<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions;

use App\Enums\BaseInheritedTraitStub;
use App\Enums\Concerns\HasLocalizationStub;
use App\Enums\Concerns\SideMenuStub;
use App\Enums\Contracts\LocalizedEnumContractStub;
use App\Enums\Contracts\SideMenuContractStub;
use App\Enums\InheritedTraitStub;
use App\Enums\MissingLocalizedContractStub;
use App\Enums\TransitiveTraitStub;
use Hihaho\PhpstanRules\Rules\Conventions\TraitRequiresInterfaceRule;
use InvalidArgumentException;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<TraitRequiresInterfaceRule>
 */
final class TraitRequiresInterfaceRuleTest extends RuleTestCase
{
    private const string LOCALIZATION_TRAIT = HasLocalizationStub::class;

    private const string LOCALIZATION_INTERFACE = LocalizedEnumContractStub::class;

    private const string SIDE_MENU_TRAIT = SideMenuStub::class;

    private const string SIDE_MENU_INTERFACE = SideMenuContractStub::class;

    private const string LOCALIZATION_TIP = 'Add App\Enums\Contracts\LocalizedEnumContractStub to the implements clause so the contract can be relied on statically.';

    private const string SIDE_MENU_TIP = 'Add App\Enums\Contracts\SideMenuContractStub to the implements clause so the contract can be relied on statically.';

    /**
     * @var array<string, string>
     */
    private array $traitRequiresInterface = [
        self::LOCALIZATION_TRAIT => self::LOCALIZATION_INTERFACE,
    ];

    #[Override]
    protected function getRule(): Rule
    {
        return new TraitRequiresInterfaceRule($this->traitRequiresInterface, self::createReflectionProvider());
    }

    #[Test]
    public function flags_a_class_using_the_trait_without_the_interface(): void
    {
        $this->analyse([__DIR__ . '/stubs/MissingLocalizedContractStub.php'], [
            [$this->localizationMessage(MissingLocalizedContractStub::class), 7, self::LOCALIZATION_TIP],
        ]);
    }

    #[Test]
    public function flags_a_class_that_gets_the_trait_through_another_trait(): void
    {
        $this->analyse([__DIR__ . '/stubs/TransitiveTraitStub.php'], [
            [$this->localizationMessage(TransitiveTraitStub::class), 7, self::LOCALIZATION_TIP],
        ]);
    }

    #[Test]
    public function flags_a_class_that_inherits_the_trait_from_its_parent(): void
    {
        $this->analyse([__DIR__ . '/stubs/InheritedTraitStub.php'], [
            [$this->localizationMessage(BaseInheritedTraitStub::class), 7, self::LOCALIZATION_TIP],
            [$this->localizationMessage(InheritedTraitStub::class), 12, self::LOCALIZATION_TIP],
        ]);
    }

    #[Test]
    public function ignores_a_class_implementing_the_interface_directly(): void
    {
        $this->analyse([__DIR__ . '/stubs/DirectLocalizedContractStub.php'], []);
    }

    #[Test]
    public function ignores_a_class_implementing_the_interface_transitively(): void
    {
        $this->analyse([__DIR__ . '/stubs/TransitiveLocalizedContractStub.php'], []);
    }

    #[Test]
    public function ignores_the_trait_declaration_itself(): void
    {
        $this->analyse([__DIR__ . '/stubs/SideMenuStub.php'], []);
    }

    #[Test]
    public function ignores_the_interface_declaration_itself(): void
    {
        $this->analyse([__DIR__ . '/stubs/LocalizedEnumContractStub.php'], []);
    }

    #[Test]
    public function ignores_a_class_using_neither_the_trait_nor_the_interface(): void
    {
        $this->analyse([__DIR__ . '/stubs/UnrelatedLocalizationStub.php'], []);
    }

    #[Test]
    public function ignores_everything_when_no_pairs_are_configured(): void
    {
        $this->traitRequiresInterface = [];

        $this->analyse([__DIR__ . '/stubs/MissingLocalizedContractStub.php'], []);
    }

    #[Test]
    public function checks_every_configured_pair(): void
    {
        $this->traitRequiresInterface = [
            self::LOCALIZATION_TRAIT => self::LOCALIZATION_INTERFACE,
            self::SIDE_MENU_TRAIT => self::SIDE_MENU_INTERFACE,
        ];

        $this->analyse([__DIR__ . '/stubs/TransitiveTraitStub.php'], [
            [$this->localizationMessage(TransitiveTraitStub::class), 7, self::LOCALIZATION_TIP],
            [
                'App\Enums\TransitiveTraitStub uses SideMenuStub but does not implement SideMenuContractStub.',
                7,
                self::SIDE_MENU_TIP,
            ],
        ]);
    }

    #[Test]
    public function matches_a_configured_pair_written_in_a_different_case(): void
    {
        $this->traitRequiresInterface = [
            strtolower(self::LOCALIZATION_TRAIT) => strtolower(self::LOCALIZATION_INTERFACE),
        ];

        $this->analyse([__DIR__ . '/stubs/MissingLocalizedContractStub.php'], [
            [$this->localizationMessage(MissingLocalizedContractStub::class), 7, self::LOCALIZATION_TIP],
        ]);
    }

    #[Test]
    public function rejects_a_configured_trait_that_does_not_exist(): void
    {
        $this->traitRequiresInterface = ['App\Enums\Concerns\MissingTrait' => self::LOCALIZATION_INTERFACE];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Parameter traitRequiresInterface: 'App\Enums\Concerns\MissingTrait' is not an existing trait.");

        $this->analyse([__DIR__ . '/stubs/MissingLocalizedContractStub.php'], []);
    }

    #[Test]
    public function rejects_a_configured_trait_that_is_not_a_trait(): void
    {
        $this->traitRequiresInterface = [self::LOCALIZATION_INTERFACE => self::LOCALIZATION_INTERFACE];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Parameter traitRequiresInterface: 'App\Enums\Contracts\LocalizedEnumContractStub' is not an existing trait.");

        $this->analyse([__DIR__ . '/stubs/MissingLocalizedContractStub.php'], []);
    }

    #[Test]
    public function rejects_a_configured_interface_that_does_not_exist(): void
    {
        $this->traitRequiresInterface = [self::LOCALIZATION_TRAIT => 'App\Enums\Contracts\MissingContract'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Parameter traitRequiresInterface: 'App\Enums\Contracts\MissingContract' is not an existing interface.");

        $this->analyse([__DIR__ . '/stubs/MissingLocalizedContractStub.php'], []);
    }

    #[Test]
    public function rejects_a_configured_interface_that_is_not_an_interface(): void
    {
        $this->traitRequiresInterface = [self::LOCALIZATION_TRAIT => self::LOCALIZATION_TRAIT];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Parameter traitRequiresInterface: 'App\Enums\Concerns\HasLocalizationStub' is not an existing interface.");

        $this->analyse([__DIR__ . '/stubs/MissingLocalizedContractStub.php'], []);
    }

    private function localizationMessage(string $className): string
    {
        return "{$className} uses HasLocalizationStub but does not implement LocalizedEnumContractStub.";
    }
}
