<?php declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void toggle(string $key, bool $on)
 */
final class FlagFacade extends Facade
{
    public static function enable(string $key, bool $on): void {}

    protected static function getFacadeAccessor(): string
    {
        return 'flags';
    }
}

/**
 * @method static void forward(string $key, bool $on)
 */
class StaticFlagBase
{
    public static function toggle(string $key, bool $on): void {}

    /** @param array<int, mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): void {}

    public static function viaStatic(): void
    {
        static::toggle('name', true);
    }

    public static function viaSelf(): void
    {
        self::toggle('name', true);
    }
}

final class MagicStaticFlagCallStub
{
    public function run(): void
    {
        FlagFacade::toggle('name', true);
        FlagFacade::enable('name', true);
        StaticFlagBase::forward('name', true);
    }
}
