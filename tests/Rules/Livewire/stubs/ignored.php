<?php declare(strict_types=1);

namespace App\Livewire\Stubs;

use Livewire\Component;

final class IgnoredWithReason extends Component
{
    public int $videoId = 0; // @phpstan-ignore hihaho.livewire.unlockedIdProperty (validated with exists before use)
}
