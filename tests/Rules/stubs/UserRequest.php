<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UserRequest extends FormRequest
{
    public function name(): string
    {
        return sprintf(
            '%s %s',
            $this->string('first_name'),
            $this->string('last_name')
        );
    }

    public function email(): string
    {
        return $this->input('email');
    }

    public function hasChildren(): bool
    {
        return $this->boolean('children');
    }

    public function childrenNames(): array
    {
        return $this->get('children')
            ->only('name');
    }
}
