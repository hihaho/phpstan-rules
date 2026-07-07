<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions;

use Hihaho\PhpstanRules\Rules\Conventions\NoEloquentWithPropertyRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<NoEloquentWithPropertyRule>
 */
final class NoEloquentWithPropertyRuleTest extends RuleTestCase
{
    private const string MESSAGE = 'Declaring Eloquent\'s $with property globally eager-loads its relations on every query. Load relations explicitly at the call site instead.';

    private const string TIP = 'Use $query->with([...]) or $model->loadMissing([...]) where the relations are actually needed.';

    #[Override]
    protected function getRule(): Rule
    {
        return new NoEloquentWithPropertyRule();
    }

    #[Test]
    public function flags_non_empty_with_property_on_a_model(): void
    {
        $this->analyse([__DIR__ . '/stubs/EloquentWithPropertyStub.php'], [
            [self::MESSAGE, 12, self::TIP],
        ]);
    }

    #[Test]
    public function flags_with_property_on_a_transitive_model_subclass(): void
    {
        $this->analyse([__DIR__ . '/stubs/TransitiveModelWithPropertyStub.php'], [
            [self::MESSAGE, 14, self::TIP],
        ]);
    }

    #[Test]
    public function flags_a_non_array_with_default(): void
    {
        $this->analyse([__DIR__ . '/stubs/ModelWithConstantWithPropertyStub.php'], [
            [self::MESSAGE, 17, self::TIP],
        ]);
    }

    #[Test]
    public function ignores_an_explicit_empty_with_property(): void
    {
        $this->analyse([__DIR__ . '/stubs/EloquentEmptyWithPropertyStub.php'], []);
    }

    #[Test]
    public function ignores_a_with_property_on_a_non_model_class(): void
    {
        $this->analyse([__DIR__ . '/stubs/NonModelWithPropertyStub.php'], []);
    }

    #[Test]
    public function ignores_a_model_without_a_with_property(): void
    {
        $this->analyse([__DIR__ . '/stubs/ModelWithoutWithPropertyStub.php'], []);
    }
}
