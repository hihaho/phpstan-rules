<?php declare(strict_types=1);

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
        return new EloquentApiResources(self::createReflectionProvider());
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/stubs/resources/Video.php'], [
            [
                'Eloquent resource App\Http\Resources\Video must be named with a `Resource` suffix, such as VideoResource.',
                7,
                'Learn more at https://guidelines.hihaho.com/laravel.html#resources',
            ],
        ]);

        $this->analyse([__DIR__ . '/stubs/resources/VideoResource.php'], []);

        $this->analyse([__DIR__ . '/stubs/resources/ChildVideo.php'], [
            [
                'Eloquent resource App\Http\Resources\ChildVideo must be named with a `Resource` suffix, such as ChildVideoResource.',
                5,
                'Learn more at https://guidelines.hihaho.com/laravel.html#resources',
            ],
        ]);

        $this->analyse([__DIR__ . '/stubs/resources/ChildVideoResource.php'], []);

        $this->analyse([__DIR__ . '/stubs/resources/RandomFile.php'], []);

        $this->analyse([__DIR__ . '/stubs/resources/VideoResourceCollection.php'], []);

        $this->analyse([__DIR__ . '/stubs/resources/VideoCollection.php'], [
            [
                'Eloquent resource collection App\Http\Resources\VideoCollection must be named with a `ResourceCollection` suffix, such as VideoResourceCollection.',
                7,
                'Learn more at https://guidelines.hihaho.com/laravel.html#resources',
            ],
        ]);
    }
}
