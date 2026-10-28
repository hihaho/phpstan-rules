<?php declare(strict_types=1);

namespace App\InheritableSettings;

class VideoStub
{
    public ?bool $autoplay = null;

    public ?string $theme = null;

    public string $title = '';

    public function resolvedAutoplay(): bool
    {
        return $this->autoplay ?? false; // @phpstan-ignore hihaho.conventions.inheritableSettingRead (the resolved getter reads the raw column)
    }
}

final class LiveVideoStub extends VideoStub {}

final class PlaylistStub
{
    public ?bool $autoplay = null;
}
