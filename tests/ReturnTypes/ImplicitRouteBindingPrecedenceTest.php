<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes;

use Override;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Explicit Route::model()/Route::bind() bindings take precedence over implicit (controller type-hint)
 * bindings for the same route parameter.
 */
final class ImplicitRouteBindingPrecedenceTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/stubs/routing/implicit/PrecedenceTarget.php');
    }

    #[DataProvider('dataFileAsserts')]
    public function testFileAsserts(string $assertType, string $file, mixed ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/route-binding-precedence-config.neon'];
    }
}
