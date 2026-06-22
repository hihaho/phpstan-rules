<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes;

use Override;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Implicit route-model binding resolution without larastan — the skip cases assert a stable default
 * route() type, which the larastan-loaded {@see ImplicitRouteBindingReturnTypeExtensionTest} can't.
 */
final class ImplicitRouteBindingStaticTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/stubs/routing/implicit/ImplicitRouteBindingStaticTarget.php');
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
        return [__DIR__ . '/route-binding-implicit-static-config.neon'];
    }
}
