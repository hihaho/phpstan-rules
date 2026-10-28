<?php declare(strict_types=1);

namespace Laravel\Nova\Fields;

/**
 * Minimal stand-in for laravel/nova fields, with the chain the rule reads.
 */
class Field
{
    public static function make(mixed ...$arguments): static
    {
        return new static();
    }

    public function asHtml(): static
    {
        return $this;
    }

    public function displayUsing(callable $callback): static
    {
        return $this;
    }

    public function resolveUsing(callable $callback): static
    {
        return $this;
    }

    public function sortable(): static
    {
        return $this;
    }
}

class Text extends Field {}
