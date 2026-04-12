<?php declare(strict_types=1);

namespace App;

class DynamicInvadeCallInAppNamespace
{
    public function test(): void
    {
        $fn = 'invade';
        $fn(new \stdClass());
    }
}
