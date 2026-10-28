<?php declare(strict_types=1);

namespace App\Nova\Stubs;

use Laravel\Nova\Fields\Text;
use Spatie\Html\Html;

final class NovaFieldClosureStubs
{
    public function fields(Html $html): array
    {
        return [
            Text::make('Title')->displayUsing(function (?string $label) {
                return '<a href="/p">' . $label . '</a>';
            })->asHtml(),
            Text::make('Resolved')->resolveUsing(function (mixed $value) {
                if ($value === null) {
                    return '&mdash;';
                }

                return $value;
            })->asHtml(),
            Text::make('Computed', function () {
                return request()->query('name');
            })->asHtml(),

            Text::make('Escaped')->asHtml()->displayUsing(function (string $name) {
                return '<b>' . e($name) . '</b>';
            }),
            Text::make('Built')->displayUsing(function (string $name) use ($html) {
                return $html->span()->text($name)->toHtml();
            })->asHtml(),
            Text::make('Plain')->displayUsing(function (string $name) {
                return $name;
            }),
        ];
    }
}
