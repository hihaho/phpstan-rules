<?php declare(strict_types=1);

namespace App;

use DateTimeImmutable;

class NonFacadeAliasStaticCall
{
    public function test(): ?DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('Y-m-d', '2025-01-01') ?: null;
    }
}
