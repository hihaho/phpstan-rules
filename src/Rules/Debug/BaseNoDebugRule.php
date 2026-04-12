<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use PHPStan\Rules\Rule;

/**
 * @template T of \PhpParser\Node
 * @implements Rule<T>
 */
abstract readonly class BaseNoDebugRule implements Rule
{
    use ChecksNamespace;

    /**
     * Debug helpers that can only appear as global function calls.
     *
     * @var list<string>
     */
    private const array FUNCTION_DEBUG_STATEMENTS = [
        'dump',
        'dd',
        'ddd',
        'ray',
        'print_r',
        'var_dump',
    ];

    /**
     * Debug helpers that can appear as method or static calls.
     *
     * `print_r` and `var_dump` are excluded — they only exist as global functions.
     *
     * @var list<string>
     */
    private const array METHOD_DEBUG_STATEMENTS = [
        'dump',
        'dd',
        'ddd',
        'ray',
    ];

    /**
     * Namespace prefix that identifies a class/trait as a Laravel-provided
     * debug helper declaration. Used to narrow chained method-call matches so
     * unrelated user methods sharing a name (e.g. a custom `->dump()`) are not
     * flagged.
     */
    protected const string LARAVEL_NAMESPACE_PREFIX = 'Illuminate\\';

    final protected function isDebugFunction(string $statement): bool
    {
        return in_array($statement, self::FUNCTION_DEBUG_STATEMENTS, true);
    }

    final protected function isDebugMethod(string $statement): bool
    {
        return in_array($statement, self::METHOD_DEBUG_STATEMENTS, true);
    }
}
