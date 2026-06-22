<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Reflection\stubs;

use function PHPStan\Testing\assertType;

final class StubbedMethodsTarget {}

final class FluentStub {}

function exerciseStubbedMethods(StubbedMethodsTarget $target): void
{
    assertType('string', $target->customString());
    assertType('int', $target->customInt());
    assertType('array<int, int>', $target->customList(5));
}

function exerciseFluentStubbedMethods(FluentStub $fluent): void
{
    // The `$this`, `static`, and `self` markers all return the receiver, so a stubbed fluent method
    // keeps its type instead of widening to a bare string.
    assertType(FluentStub::class, $fluent->thisChain());
    assertType(FluentStub::class, $fluent->staticChain());
    assertType(FluentStub::class, $fluent->selfChain());

    // The chain keeps resolving the receiver's stubbed methods on the returned type.
    assertType(FluentStub::class, $fluent->thisChain()->staticChain());
    assertType('string', $fluent->thisChain()->label());
}
