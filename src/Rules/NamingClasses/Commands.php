<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\NamingClasses;

use Illuminate\Console\Command;

/**
 * @see https://guidelines.hihaho.com/laravel.html#commands
 */
class Commands extends SuffixableRule
{
    public function baseClass(): string
    {
        return Command::class;
    }

    public function name(): string
    {
        return 'Command';
    }

    public function suffix(): string
    {
        return 'Command';
    }

    public function docs(): string
    {
        return 'https://guidelines.hihaho.com/laravel.html#commands';
    }
}
