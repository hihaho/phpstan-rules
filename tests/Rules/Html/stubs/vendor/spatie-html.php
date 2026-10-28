<?php declare(strict_types=1);

namespace Spatie\Html;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Minimal stand-in for spatie/laravel-html, with the signatures the rule reads.
 */
class BaseElement implements Htmlable
{
    public function html(mixed $html): static
    {
        return $this;
    }

    public function text(mixed $text): static
    {
        return $this;
    }

    public function children(mixed $children, ?callable $mapper = null): static
    {
        return $this;
    }

    public function class(string $class): static
    {
        return $this;
    }

    public function toHtml(): string
    {
        return '';
    }
}

class Html
{
    public function a(?string $href = null, mixed $contents = null): BaseElement
    {
        return new BaseElement();
    }

    public function span(mixed $contents = null): BaseElement
    {
        return new BaseElement();
    }

    public function div(mixed $contents = null): BaseElement
    {
        return new BaseElement();
    }

    public function p(mixed $contents = null): BaseElement
    {
        return new BaseElement();
    }
}

namespace Spatie\Html\Facades;

/**
 * @method static \Spatie\Html\BaseElement span(mixed $contents = null)
 * @method static \Spatie\Html\BaseElement a(?string $href = null, mixed $contents = null)
 */
class Html
{
    /**
     * @param  array<mixed>  $arguments
     */
    public static function __callStatic(string $method, array $arguments): \Spatie\Html\BaseElement
    {
        return new \Spatie\Html\BaseElement();
    }
}
