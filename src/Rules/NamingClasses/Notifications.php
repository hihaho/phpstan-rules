<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\NamingClasses;

use Illuminate\Notifications\Notification;

/**
 * @see https://guidelines.hihaho.com/laravel.html#notifications
 */
class Notifications extends SuffixableRule
{
    public function baseClass(): string
    {
        return Notification::class;
    }

    public function name(): string
    {
        return 'Notification';
    }

    public function suffix(): string
    {
        return 'Notification';
    }
}
