<?php declare(strict_types=1);

namespace App\InheritableSettings;

final class InheritableSettingReads
{
    public function read(VideoStub $video, ?VideoStub $maybe, LiveVideoStub $live, PlaylistStub $playlist): void
    {
        $autoplay = $video->autoplay;
        $theme = $maybe?->theme;
        $inherited = $live->autoplay;
        $isSet = isset($video->theme);

        $title = $video->title;
        $other = $playlist->autoplay;
        $video->autoplay = null;
        $video->theme = 'dark';
    }
}
