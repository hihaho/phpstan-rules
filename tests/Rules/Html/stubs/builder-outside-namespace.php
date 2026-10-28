<?php declare(strict_types=1);

namespace Vendor\Library;

use Spatie\Html\Html;

final class BuilderOutsideNamespaceStub
{
    public function render(Html $html, string $label): void
    {
        $html->span($label);
    }
}
