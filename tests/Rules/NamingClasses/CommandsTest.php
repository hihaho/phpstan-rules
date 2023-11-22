<?php declare(strict_types=1);

namespace Rules\NamingClasses;

use Hihaho\PhpstanRules\Rules\NamingClasses\Commands;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<Commands>
 */
class CommandsTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new Commands($this->createReflectionProvider());
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/stubs/commands/NotifyUsers.php'], [
            [
                'Command App\Console\Commands\NotifyUsers must be named with a `Command` suffix, such as NotifyUsersCommand.',
                7,
                'Learn more at https://guidelines.hihaho.com/laravel.html#commands',
            ],
        ]);

        $this->analyse([__DIR__ . '/stubs/commands/FromBaseWithoutSuffix.php'], [
            [
                'Command App\Console\Commands\FromBaseWithoutSuffix must be named with a `Command` suffix, such as FromBaseWithoutSuffixCommand.',
                5,
                'Learn more at https://guidelines.hihaho.com/laravel.html#commands',
            ],
        ]);

        $this->analyse([__DIR__ . '/stubs/commands/SendMailCommand.php'], []);

        $this->analyse([__DIR__ . '/stubs/commands/Base.php'], []);

        $this->analyse([__DIR__ . '/stubs/commands/FromBaseCommand.php'], []);
    }
}
