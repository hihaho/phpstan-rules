<?php declare(strict_types = 1);

namespace Rules\NamingClasses;

use Hihaho\PhpstanRules\Rules\NamingClasses\EloquentApiResources;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<EloquentApiResources>
 */
class EloquentApiResourcesTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new EloquentApiResources();
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/stubs/Video.php'], [
            [
                'Eloquent resources must be named with a `Resources` suffix, such as VideoResource.',
                5,
            ],
        ]);

        $this->analyse([__DIR__ . '/stubs/VideoResource.php'], []);

        $this->analyse([__DIR__ . '/stubs/RandomFile.php'], []);
    }
}
