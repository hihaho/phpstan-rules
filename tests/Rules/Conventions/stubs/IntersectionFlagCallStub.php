<?php declare(strict_types=1);

namespace Vendor\Toggles;

interface VendorRenders
{
    public function render(bool $short): void;
}

namespace App\Services;

use Vendor\Toggles\VendorRenders;

interface TogglesActive
{
    public function setActive(string $key, bool $active): void;
}

interface HasExtras
{
    public function addExtra(string $key): void;
}

interface RendersShort
{
    public function render(bool $short): void;
}

interface AlsoRendersShort
{
    public function render(bool $short): void;
}

interface RendersCompact
{
    public function render(bool $compact): void;
}

final class IntersectionFlagCallStub
{
    public function oneDeclarer(TogglesActive&HasExtras $subject): void
    {
        $subject->setActive('name', true);
    }

    public function agreeingDeclarers(RendersShort&AlsoRendersShort $subject): void
    {
        $subject->render(true);
    }

    public function disagreeingDeclarers(RendersShort&RendersCompact $subject): void
    {
        $subject->render(true);
    }

    public function vendorDeclarer(RendersShort&VendorRenders $subject): void
    {
        $subject->render(true);
    }

    public function unionOneDeclarer(RendersShort|HasExtras $subject): void
    {
        $subject->render(true);
    }
}
