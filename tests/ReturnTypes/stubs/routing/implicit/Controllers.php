<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit;

final class AdaptiveSubjectController
{
    // snake_case route param {adaptive_learning_subject} matches camelCase $adaptiveLearningSubject.
    public function __invoke(Video $video, AdaptiveLearningSubject $adaptiveLearningSubject): void {}
}

final class ContainerController
{
    public function show(VideoContainer $videoContainer): void {}
}

final class VideoController
{
    public function edit(Video $video): void {}
}

final class AppleController
{
    public function __invoke(Apple $item): void {}
}

final class BananaController
{
    public function __invoke(Banana $item): void {}
}

final class UnhintedController
{
    public function __invoke(string $loose): void {}
}

final class OptionalVideoController
{
    public function __invoke(Video $optionalVideo): void {}
}
