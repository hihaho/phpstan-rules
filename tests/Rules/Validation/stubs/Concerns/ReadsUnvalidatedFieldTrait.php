<?php declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait ReadsUnvalidatedFieldTrait
{
    public function readFromTrait(): string
    {
        return (string) $this->string('from_trait');
    }
}
