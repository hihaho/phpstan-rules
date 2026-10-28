<?php declare(strict_types=1);

namespace App\Livewire\Stubs;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Form;

final class EditVideo extends Component
{
    public int $videoId = 0;

    public ?string $containerUuid = null;

    /** @var list<int> */
    public array $selectedQuestionIds = [];

    public int $folder_id = 0;

    public $untypedId;

    public int $firstId = 0, $secondId = 0;

    #[Locked]
    public int $lockedId = 0;

    public bool $showId = false;

    public static int $staticId = 0;

    protected int $protectedId = 0;

    private int $privateId = 0;

    public string $title = '';

    public string $identifier = '';
}

final class VideoForm extends Form
{
    public int $videoId = 0;
}

final class NestedEditVideo extends Base
{
    public int $playlistId = 0;
}

abstract class Base extends Component {}

final class NotLivewire
{
    public int $videoId = 0;
}
