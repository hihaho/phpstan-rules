<?php declare(strict_types=1);

namespace App\Nova\Stubs;

use App\Html\Stubs\Status;
use Laravel\Nova\Fields\Text;
use Spatie\Html\Html;

final class NovaFieldArrowFunctionStubs
{
    public string $title = '';

    public function fields(Html $html): array
    {
        return [
            Text::make('Name')->asHtml()->displayUsing(fn (string $name) => "<b>{$name}</b>"),
            Text::make('Raw', 'raw', fn () => $this->title)->asHtml(),
            Text::make('Computed', fn () => $this->title)->sortable()->asHtml(),
            Text::make('Fallback')->displayUsing(fn (?string $name) => $name ?? '&mdash;')->asHtml(),

            Text::make('Escaped')->displayUsing(fn (string $name) => '<b>' . e($name) . '</b>')->asHtml(),
            Text::make('Built')->displayUsing(fn (string $name) => $html->span()->text($name)->toHtml())->asHtml(),
            Text::make('Element')->displayUsing(fn (string $name) => $html->span()->text($name))->asHtml(),
            Text::make('Count')->displayUsing(fn (int $count) => "<b>{$count}</b>")->asHtml(),
            Text::make('Status')->displayUsing(fn (Status $status) => $status)->asHtml(),
            Text::make('Literal')->displayUsing(fn () => '<i>none</i>')->asHtml(),
            Text::make('Plain')->displayUsing(fn (string $name) => $name),
            Text::make('Ignored')->displayUsing(fn (string $markup) => $markup)->asHtml(), // @phpstan-ignore hihaho.html.unescapedHtmlField (markup is sanitised on save)
        ];
    }
}
