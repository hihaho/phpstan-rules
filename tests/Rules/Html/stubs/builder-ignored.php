<?php declare(strict_types=1);

namespace App\Html\Stubs;

use Spatie\Html\Html;

final class BuilderIgnoredStub
{
    public function render(Html $html, string $trustedMarkup): void
    {
        $html->div($trustedMarkup); // @phpstan-ignore hihaho.html.unescapedBuilderContent (sanitised by HtmlPurifier upstream)
    }
}
