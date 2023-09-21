<?php declare(strict_types=1);

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
            ],
            [
                'A route URL should be / instead of an empty string.',
                7,
            ],
            [
                'A route URL should be / instead of an empty string.',
                10,
            ],
            [
                'A route URL should not start or end with /.',
                14,
            ],
            [
                'A route URL should not start or end with /.',
                15,
            ],
            [
                'A route URL should not start or end with /.',
                16,
            ],
        ]);
    }
}
