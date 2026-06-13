<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class NoRulesMethodFormRequestStub extends FormRequest
{
    public function describe(): string
    {
        return (string) $this->string('name');
    }
}
