<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

trait HasUrlTip
{
    public function tip(): string
    {
        return "Learn more at {$this->docs()}";
    }

    abstract public function docs(): string;
}
