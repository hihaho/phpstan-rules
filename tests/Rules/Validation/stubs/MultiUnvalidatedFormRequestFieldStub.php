<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MultiUnvalidatedFormRequestFieldStub extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    public function summary(): string
    {
        $title = $this->string('title');
        $count = $this->integer('count');
        $file = $this->file('avatar');

        return "{$title} {$count} {$file->getClientOriginalName()}";
    }
}
