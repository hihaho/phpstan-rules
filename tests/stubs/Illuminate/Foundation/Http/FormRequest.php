<?php declare(strict_types=1);

namespace Illuminate\Foundation\Http;

use Illuminate\Http\Concerns\InteractsWithInput;
use Illuminate\Support\ValidatedInput;
use Symfony\Component\HttpFoundation\Request;

abstract class FormRequest extends Request
{
    use InteractsWithInput;

    /**
     * The validator instance.
     *
     * @var \Illuminate\Contracts\Validation\Validator
     */
    protected $validator;

    /**
     * Get a validated input container for the validated input.
     *
     * @return \Illuminate\Support\ValidatedInput|array
     */
    public function safe(?array $keys = null)
    {
        return is_array($keys)
            ? (new ValidatedInput($this->validated()))->only($keys)
            : new ValidatedInput($this->validated());
    }

    /**
     * This method belongs to Symfony HttpFoundation and is not usually needed when using Laravel.
     *
     * Instead, you may use the "input" method.
     */
    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return parent::get($key, $default);
    }

    public function validated(): array
    {
        return [];
    }
}
