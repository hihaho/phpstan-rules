<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Support;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PHPStan\Reflection\ParameterReflection;

/**
 * Finds the bare bool/null flag arguments at the end of a call that can be named
 * without forcing any other positional argument to be named: the flags themselves
 * plus any already-named arguments after them. A positional non-flag ends the run,
 * because a positional argument after a named one is a fatal error.
 *
 * @internal
 */
final class TrailingFlagRun
{
    /**
     * The cheap reject for the hot path: needs no reflection, so a call without a
     * flag in its trailing run never pays for resolving its callee.
     *
     * @param  array<Arg>  $args
     */
    public static function mayContainFlag(array $args): bool
    {
        foreach (array_reverse($args) as $arg) {
            if ($arg->name instanceof Identifier) {
                continue;
            }

            return self::isBareFlag($arg);
        }

        return false;
    }

    /**
     * Indexes of the arguments to name, ascending; empty when there is nothing to name.
     *
     * An unpacked argument anywhere declines the call: it hides how many positions
     * the arguments after it occupy, so an index no longer maps to a parameter.
     *
     * @param  list<Arg>  $args
     * @param  list<ParameterReflection>  $parameters
     * @return list<int>
     */
    public static function indexes(array $args, array $parameters): array
    {
        if (array_any($args, static fn (Arg $arg): bool => $arg->unpack)) {
            return [];
        }

        $indexes = [];

        foreach (array_reverse($args, preserve_keys: true) as $index => $arg) {
            if ($arg->name instanceof Identifier) {
                continue;
            }

            $parameter = $parameters[$index] ?? null;

            if (! $parameter instanceof ParameterReflection || $parameter->isVariadic() || ! self::isBareFlag($arg)) {
                break;
            }

            $indexes[] = $index;
        }

        return array_reverse($indexes);
    }

    public static function isBareFlag(Arg $arg): bool
    {
        if ($arg->name instanceof Identifier || $arg->unpack || ! $arg->value instanceof ConstFetch) {
            return false;
        }

        return match ($arg->value->name->toLowerString()) {
            'true', 'false', 'null' => true,
            default => false,
        };
    }
}
