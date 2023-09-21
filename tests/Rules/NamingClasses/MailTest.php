<?php declare(strict_types = 1);

namespace Rules\NamingClasses;

use Hihaho\PhpstanRules\Rules\NamingClasses\Mail;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<Mail>
 */
class MailTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new Mail($this->createReflectionProvider());
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/stubs/mail/AccountActivated.php'], [
            [
                'Mailable App\Mail\AccountActivated must be named with a `Mail` suffix, such as AccountActivatedMail.',
                7,
            ],
        ]);

        $this->analyse([__DIR__ . '/stubs/mail/AccountActivatedMail.php'], []);
    }
}
