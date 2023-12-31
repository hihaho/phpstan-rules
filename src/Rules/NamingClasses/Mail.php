<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\NamingClasses;

use Illuminate\Mail\Mailable;

/**
 * @see https://guidelines.hihaho.com/laravel.html#mailables
 */
class Mail extends SuffixableRule
{
    public function baseClass(): string
    {
        return Mailable::class;
    }

    public function name(): string
    {
        return 'Mailable';
    }

    public function suffix(): string
    {
        return 'Mail';
    }

    public function docs(): string
    {
        return 'https://guidelines.hihaho.com/laravel.html#mailables';
    }
}
