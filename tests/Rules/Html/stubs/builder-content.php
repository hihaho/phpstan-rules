<?php declare(strict_types=1);

namespace App\Html\Stubs;

use Spatie\Html\BaseElement;
use Spatie\Html\Facades\Html as HtmlFacade;
use Spatie\Html\Html;

enum Status: string
{
    case Active = 'active';
}

final class BuilderContentStubs
{
    public function flagged(Html $html, BaseElement $element, string $label, mixed $value): void
    {
        $html->a('/posts', $label);
        $html->span("Label: {$label}");
        $html->div('<b>' . $label . '</b>');
        $html->p(sprintf('<b>%s</b>', $label));
        $element->html($value);
        $element->children([$label]);
        $html->a(contents: $label, href: '/x');
        HtmlFacade::span($label);
    }

    public function safe(Html $html, BaseElement $element, string $label, int $count, ?float $ratio, bool $flag, Status $status): void
    {
        $html->a('/posts', 'Posts');
        $html->a($label);
        $html->span($count);
        $html->span($ratio);
        $html->span($flag);
        $html->span($status);
        $html->span(e($label));
        $html->div('<b>' . htmlspecialchars($label) . '</b>');
        $html->span()->text($label);
        $html->div($html->span()->text($label));
        $element->children([$html->span()->text($label)]);
        $element->children([$label], fn (string $item): BaseElement => $html->span()->text($item));
        $html->div($element->toHtml());
        HtmlFacade::span('Static label');
        $this->span($label);
    }

    private function span(string $value): string
    {
        return $value;
    }
}
