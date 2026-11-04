<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Support;

use Hihaho\PhpstanRules\Support\TrailingFlagRun;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Reflection\ParameterReflection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TrailingFlagRunTest extends TestCase
{
    #[Test]
    public function names_a_single_trailing_flag(): void
    {
        $this->assertSame([1], TrailingFlagRun::indexes([$this->value(), $this->flag()], $this->parameters(2)));
    }

    #[Test]
    public function names_every_flag_in_a_consecutive_run(): void
    {
        $this->assertSame([1, 2], TrailingFlagRun::indexes([$this->value(), $this->flag('true'), $this->flag('null')], $this->parameters(3)));
    }

    #[Test]
    public function names_a_flag_before_an_already_named_argument_and_not_the_named_one(): void
    {
        $this->assertSame([1], TrailingFlagRun::indexes([$this->value(), $this->flag(), $this->named()], $this->parameters(3)));
    }

    #[Test]
    public function stops_at_a_positional_non_flag(): void
    {
        $this->assertSame([], TrailingFlagRun::indexes([$this->flag(), $this->value()], $this->parameters(2)));
        $this->assertSame([2], TrailingFlagRun::indexes([$this->flag(), $this->value(), $this->flag()], $this->parameters(3)));
    }

    #[Test]
    public function declines_when_any_argument_is_unpacked(): void
    {
        $unpacked = new Arg(new Variable('rest'), unpack: true);

        $this->assertSame([], TrailingFlagRun::indexes([$this->flag(), $unpacked], $this->parameters(2)));
        $this->assertSame([], TrailingFlagRun::indexes([$unpacked, $this->flag()], $this->parameters(2)));
    }

    #[Test]
    public function stops_at_a_missing_parameter(): void
    {
        $this->assertSame([], TrailingFlagRun::indexes([$this->flag(), $this->flag()], $this->parameters(1)));
    }

    #[Test]
    public function stops_at_a_variadic_parameter(): void
    {
        $parameters = [$this->parameter(variadic: false), $this->parameter(variadic: true)];

        $this->assertSame([], TrailingFlagRun::indexes([$this->flag(), $this->flag()], $parameters));
    }

    #[Test]
    public function has_nothing_to_name_without_a_flag(): void
    {
        $this->assertSame([], TrailingFlagRun::indexes([], []));
        $this->assertSame([], TrailingFlagRun::indexes([$this->named()], $this->parameters(1)));
    }

    #[Test]
    public function may_contain_a_flag_only_when_the_last_positional_argument_is_one(): void
    {
        $this->assertTrue(TrailingFlagRun::mayContainFlag([$this->flag()]));
        $this->assertTrue(TrailingFlagRun::mayContainFlag([$this->flag(), $this->named()]));
        $this->assertFalse(TrailingFlagRun::mayContainFlag([]));
        $this->assertFalse(TrailingFlagRun::mayContainFlag([$this->named()]));
        $this->assertFalse(TrailingFlagRun::mayContainFlag([$this->flag(), $this->value()]));
        $this->assertFalse(TrailingFlagRun::mayContainFlag([new Arg(new Variable('rest'), unpack: true)]));
    }

    #[Test]
    public function a_bare_flag_is_a_positional_true_false_or_null_in_any_case(): void
    {
        $this->assertTrue(TrailingFlagRun::isBareFlag($this->flag('TRUE')));
        $this->assertTrue(TrailingFlagRun::isBareFlag($this->flag('false')));
        $this->assertTrue(TrailingFlagRun::isBareFlag($this->flag('Null')));
        $this->assertFalse(TrailingFlagRun::isBareFlag($this->flag('PHP_EOL')));
        $this->assertFalse(TrailingFlagRun::isBareFlag($this->value()));
        $this->assertFalse(TrailingFlagRun::isBareFlag($this->named()));
    }

    private function flag(string $literal = 'true'): Arg
    {
        return new Arg(new ConstFetch(new Name($literal)));
    }

    private function value(): Arg
    {
        return new Arg(new Variable('value'));
    }

    private function named(): Arg
    {
        return new Arg(new ConstFetch(new Name('true')), name: new Identifier('named'));
    }

    /**
     * @return list<ParameterReflection>
     */
    private function parameters(int $count): array
    {
        return array_map(fn (int $index): ParameterReflection => $this->parameter(variadic: false), $count === 0 ? [] : range(0, $count - 1));
    }

    private function parameter(bool $variadic): ParameterReflection
    {
        $parameter = self::createStub(ParameterReflection::class);
        $parameter->method('isVariadic')->willReturn($variadic);

        return $parameter;
    }
}
