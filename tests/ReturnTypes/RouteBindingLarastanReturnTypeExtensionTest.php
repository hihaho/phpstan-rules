<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes;

use Override;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Runs the route-binding extension with larastan loaded — the environment every real consumer uses,
 * which the {@see RouteBindingReturnTypeExtensionTest} (Laravel-only) cannot cover.
 */
final class RouteBindingLarastanReturnTypeExtensionTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/stubs/routing/RouteBindingLarastanTarget.php');
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
        return [__DIR__ . '/route-binding-larastan-config.neon'];
    }
}
