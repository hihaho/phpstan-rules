<?php declare(strict_types=1);

namespace Rules\Routing\SlashInUrl;

use App\Facades\Custom;
use Hihaho\PhpstanRules\Rules\OnlyAllowFacadeAliasInBlade;
use Illuminate\Support\Facades\Route;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<OnlyAllowFacadeAliasInBlade>
 */
class OnlyAllowFacadeAliasInBladeTest extends RuleTestCase
{
    protected $aliases = [
        \Route::class => Route::class, // @phpstan-ignore-line
        \Custom::class => Custom::class, // @phpstan-ignore-line
    ];

    public function setUp(): void
    {
        parent::setUp();

        /**
         * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Foundation/AliasLoader.php
         */
        spl_autoload_register([$this, 'autoload'], throw: true, prepend: true);
    }

    public function autoload(string $alias)
    {
        if ($alias === Custom::class) {
            include 'stubs/CustomFacade.php';
        }

        if (isset($this->aliases[$alias])) {
            return class_alias($this->aliases[$alias], $alias);
        }
    }

    protected function getRule(): Rule
    {
        return new OnlyAllowFacadeAliasInBlade();
    }

    public function testRule(): void
    {
        // Blade files are basically ignored, since it doesn't really analyse them.
        // Statements like @php and {{ ... }} can't be parsed by PHPStan.
        // PHPStan does properly analyse the <?php tag, so it will be parsed.
        $this->analyse([__DIR__ . '/stubs/facade-alias-in-view.blade.php'], []);

        $this->analyse([__DIR__ . '/stubs/FullFacadeNamespaceInClass.php'], []);

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
}
