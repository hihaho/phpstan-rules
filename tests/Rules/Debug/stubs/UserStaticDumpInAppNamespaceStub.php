<?php declare(strict_types=1);

namespace App;

final class UserStaticDumpable
{
    public static function dump(string $value): string
    {
        return "User debug: {$value}";
    }

    public static function dd(string $value): string
    {
        return "User dd: {$value}";
    }
}

final class UsesUserStaticDumpable
{
    public function test(): void
    {
        UserStaticDumpable::dump('value');
        UserStaticDumpable::dd('value');
    }
}
