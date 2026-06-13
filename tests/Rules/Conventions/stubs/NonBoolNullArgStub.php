<?php declare(strict_types=1);

namespace App\Services;

final class NonBoolTarget
{
    public function find(?int $id): void {}

    public function rename(?string $name): void {}
}

final class NonBoolNullArgStub
{
    public function run(): void
    {
        $target = new NonBoolTarget();

        $target->find(null);
        $target->rename(null);
    }
}
