<?php declare(strict_types=1);

namespace App;

final class CustomDumpable
{
    public function dump(): string
    {
        return 'domain value';
    }

    public function dd(): string
    {
        return 'another domain value';
    }
}

final class UsesCustomDumpable
{
    public function test(CustomDumpable $obj): void
    {
        $obj->dump();
        $obj->dd();
    }
}
