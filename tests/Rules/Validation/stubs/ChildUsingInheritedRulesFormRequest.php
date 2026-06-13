<?php declare(strict_types=1);

namespace App\Http\Requests;

final class ChildUsingInheritedRulesFormRequest extends SharedRulesBaseFormRequest
{
    public function describe(): string
    {
        $shared = $this->string('shared');
        $other = $this->string('not_shared');

        return "{$shared}{$other}";
    }
}
