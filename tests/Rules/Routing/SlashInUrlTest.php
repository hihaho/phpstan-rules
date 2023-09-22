<?php declare(strict_types = 1);

namespace Rules\Routing\SlashInUrl;

use Hihaho\PhpstanRules\Rules\Routing\SlashInUrl;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<SlashInUrl>
 */
class SlashInUrlTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new SlashInUrl();
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/stubs/routes/slash-in-url.php'], [
            [
                'A route URL should be / instead of an empty string.',
                6,
                'Learn more at https://guidelines.hihaho.com/laravel.html#slash-in-url',
            ],
            [
                'A route URL should be / instead of an empty string.',
                7,
                'Learn more at https://guidelines.hihaho.com/laravel.html#slash-in-url',
            ],
            [
                'A route URL should be / instead of an empty string.',
                10,
                'Learn more at https://guidelines.hihaho.com/laravel.html#slash-in-url',
            ],
            [
                'A route URL should not start or end with /.',
                14,
                'Learn more at https://guidelines.hihaho.com/laravel.html#slash-in-url',
            ],
            [
                'A route URL should not start or end with /.',
                15,
                'Learn more at https://guidelines.hihaho.com/laravel.html#slash-in-url',
            ],
            [
                'A route URL should not start or end with /.',
                16,
                'Learn more at https://guidelines.hihaho.com/laravel.html#slash-in-url',
            ],
        ]);
    }
}
