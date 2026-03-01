<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use PHPStan\Rules\Rule;

/**
 * @template T of \PhpParser\Node
 * @implements \PHPStan\Rules\Rule<T>
 */
abstract class BaseNoDebugRule implements Rule
{
    use ChecksNamespace;

    /**
     * @var list<string>
     */
    protected array $debugStatements = [
        'dump',
        'dd',
        'ddd',
        'ray',
        'print_r',
        'var_dump',
    ];

    protected function isDisallowedStatement(string $statement): bool
    {
        return in_array($statement, $this->debugStatements, true);
    }
}
