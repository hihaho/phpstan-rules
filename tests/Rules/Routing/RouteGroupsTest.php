<?php declare(strict_types=1);

namespace Rules\Routing\SlashInUrl;

use Hihaho\PhpstanRules\Rules\Routing\RouteGroups;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<RouteGroups>
 */
class RouteGroupsTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RouteGroups();
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/stubs/routes/route-groups.php'], [
            [
                'Route group options should be defined using methods.',
                9,
                'Learn more at https://guidelines.hihaho.com/laravel.html#route-groups',
            ],
        ]);
    }
}
