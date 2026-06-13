<?php declare(strict_types=1);

namespace App\Services;

final class NonFormRequestFieldStub
{
    public function boolean(string $key): bool
    {
        return $key !== '';
    }

    public function run(): bool
    {
        return $this->boolean('submit_redirect');
    }
}
