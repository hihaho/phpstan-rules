<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Livewire;

use Hihaho\PhpstanRules\Rules\Livewire\UnlockedIdPropertyRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<UnlockedIdPropertyRule>
 */
final class UnlockedIdPropertyRuleTest extends RuleTestCase
{
    private const string TIP = 'Add #[Locked]. If the user picks this id on purpose and the server validates or scopes it, add @phpstan-ignore hihaho.livewire.unlockedIdProperty (reason).';

    /** @var list<string> */
    private array $namespaces = ['App'];

    /** @var list<string> */
    private array $excludeNamespaces = [];

    #[Override]
    protected function getRule(): Rule
    {
        return new UnlockedIdPropertyRule($this->namespaces, $this->excludeNamespaces);
    }

    /**
     * Not reported in the fixture: a #[Locked] id, a bool, a static property,
     * protected and private properties, names that do not end in an id suffix,
     * and a class that is not a Livewire component.
     */
    #[Test]
    public function flags_public_id_properties_without_locked(): void
    {
        $this->analyse([__DIR__ . '/stubs/components.php'], [
            [$this->message('EditVideo', 'videoId'), 11, self::TIP],
            [$this->message('EditVideo', 'containerUuid'), 13, self::TIP],
            [$this->message('EditVideo', 'selectedQuestionIds'), 16, self::TIP],
            [$this->message('EditVideo', 'folder_id'), 18, self::TIP],
            [$this->message('EditVideo', 'untypedId'), 20, self::TIP],
            [$this->message('EditVideo', 'firstId'), 22, self::TIP],
            [$this->message('EditVideo', 'secondId'), 22, self::TIP],
            [$this->message('VideoForm', 'videoId'), 42, self::TIP],         // form object
            [$this->message('NestedEditVideo', 'playlistId'), 47, self::TIP], // component through a base class
        ]);
    }

    #[Test]
    public function accepts_an_ignore_comment_with_a_reason(): void
    {
        $this->analyse([__DIR__ . '/stubs/ignored.php'], []);
    }

    #[Test]
    public function ignores_excluded_namespaces(): void
    {
        $this->excludeNamespaces = ['App\Livewire'];

        $this->analyse([__DIR__ . '/stubs/components.php'], []);
    }

    #[Test]
    public function reports_nothing_when_no_namespaces_are_configured(): void
    {
        $this->namespaces = [];

        $this->analyse([__DIR__ . '/stubs/components.php'], []);
    }

    private function message(string $class, string $property): string
    {
        return "Public Livewire property App\\Livewire\\Stubs\\{$class}::\${$property} holds an id but is not #[Locked], so the browser can change it.";
    }
}
