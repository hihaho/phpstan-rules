<?php declare(strict_types=1);

namespace Rules\NamingClasses;

use Hihaho\PhpstanRules\Rules\NamingClasses\Notifications;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<Notifications>
 */
class NotificationTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new Notifications($this->createReflectionProvider());
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/stubs/notification/ResetPassword.php'], [
            [
                'Notification App\Notifications\ResetPassword must be named with a `Notification` suffix, such as ResetPasswordNotification.',
                7,
                'Learn more at https://guidelines.hihaho.com/laravel.html#notifications',
            ],
        ]);

        $this->analyse([__DIR__ . '/stubs/notification/ResetPasswordNotification.php'], []);
    }
}
