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
        $this->analyse([__DIR__ . '/stubs/resources/Video.php'], [
            [
                'Eloquent resource App\Http\Resources\Video must be named with a `Resource` suffix, such as VideoResource.',
                7,
            ],
        ]);

        $this->analyse([__DIR__ . '/stubs/resources/VideoResource.php'], []);

        $this->analyse([__DIR__ . '/stubs/resources/RandomFile.php'], []);
    }
}
