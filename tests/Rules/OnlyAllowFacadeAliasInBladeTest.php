<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules;

use App\Facades\Custom;
use Hihaho\PhpstanRules\Rules\OnlyAllowFacadeAliasInBlade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<OnlyAllowFacadeAliasInBlade>
 */
final class OnlyAllowFacadeAliasInBladeTest extends RuleTestCase
{
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
         * Register Laravel's alias loader pattern — a lazy SPL autoloader
         * that resolves the short facade name only on first access.
         *
         * This deliberately mirrors Laravel's real-world behaviour rather
         * than eagerly calling `class_alias()` up front: the rule must work
         * under lazy loading, because that's what consumers actually have.
         * An eager setUp would make the test pass even if the rule used a
         * static reflection path that never triggers runtime autoloaders.
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

    protected function getRule(): Rule
    {
        return new OnlyAllowFacadeAliasInBlade();
    }

    public function testBladeFilesAreIgnored(): void
    {
        // Blade files are basically ignored, since PHPStan doesn't really analyse them.
        // Statements like @php and {{ ... }} can't be parsed by PHPStan.
        // PHPStan does properly analyse the <?php tag, so it will be parsed.
        $this->analyse([__DIR__ . '/stubs/facade-alias-in-view.blade.php'], []);
    }

    public function testFullyQualifiedFacadeUsageIsAllowed(): void
    {
        $this->analyse([__DIR__ . '/stubs/FullFacadeNamespaceInClass.php'], []);
    }

    public function testFacadeAliasInClassIsFlagged(): void
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

    public function testShouldNotCrashOnNonExistentClass(): void
    {
        $this->analyse([__DIR__ . '/stubs/NonExistentClassStaticCall.php'], []);
    }

    public function testShouldNotFlagNonFacadeAlias(): void
    {
        $this->analyse([__DIR__ . '/stubs/NonFacadeAliasStaticCall.php'], []);
    }

    public function testShouldNotFlagDynamicStaticCall(): void
    {
        // Branch: `$node->class` is not `Node\Name` (dynamic: `$class::method()`).
        $this->analyse([__DIR__ . '/stubs/DynamicStaticCallInAppNamespace.php'], []);
    }

    public function testShouldHaveCorrectErrorIdentifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/FacadeAliasInClass.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.generic.onlyAllowFacadeAliasInBlade', $error->getIdentifier());
        }
    }
}
