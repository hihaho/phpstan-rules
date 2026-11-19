<?php declare(strict_types=1);

namespace App\Models;

class Gadget
{
    public function __construct(public string $name, public bool $shown) {}

    public static function make(): static
    {
        return new static('name', true);
    }

    public static function copy(): self
    {
        return new self('name', true);
    }
}
