<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Reflection\stubs;

use function PHPStan\Testing\assertType;

final class StubbedMethodsTarget {}

function exerciseStubbedMethods(StubbedMethodsTarget $target): void
{
    assertType('string', $target->customString());
    assertType('int', $target->customInt());
    assertType('array<int, int>', $target->customList(5));
}
