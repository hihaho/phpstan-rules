<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

/**
 * Finds raw `ALTER TABLE` statements naming an outlier table without spelling out
 * ALGORITHM=INSTANT.
 *
 * Every string in the class is inspected rather than only `DB::statement()`
 * arguments — `DB::unprepared()`, a connection-scoped call and a statement assembled
 * into a variable all reach the same server, and enumerating the callers is how the
 * shell gate this replaces kept finding a new hole.
 *
 * @internal collaborator of SlowMigrationDdlRule; not part of the package's API.
 */
final readonly class RawAlterScanner
{
    /**
     * @param  list<string>  $outlierTables
     */
    public function __construct(
        private array $outlierTables,
    ) {}

    /**
     * @return list<array{int, string|null}> line, and the outlier the statement alters —
     *                                       null when the target could not be read
     *                                       statically at all
     */
    public function scan(Class_ $class, MigrationTableNameResolver $resolver): array
    {
        $findings = [];

        foreach ($this->outermostStrings($class) as $string) {
            $sql = $resolver->flatten($string);

            if ($sql === null) {
                continue;
            }

            if (preg_match('/ALTER\s+TABLE/i', $sql) !== 1) {
                continue;
            }

            if (preg_match('/ALGORITHM\s*=\s*INSTANT/i', $sql) === 1) {
                continue;
            }

            $table = $this->outlierAlteredBy($sql);

            if ($table === null && ! $this->targetIsUnreadable($sql)) {
                continue;
            }

            $findings[] = [$string->getStartLine(), $table];
        }

        return $findings;
    }

    /**
     * Concatenations and interpolations are read whole; the literals nested inside
     * them are skipped so one statement is reported once.
     *
     * @return list<Expr>
     */
    private function outermostStrings(Class_ $class): array
    {
        $finder = new NodeFinder();

        /** @var list<Expr> $composites */
        $composites = [
            ...$finder->findInstanceOf($class, Concat::class),
            ...$finder->findInstanceOf($class, InterpolatedString::class),
        ];

        $nested = [];

        foreach ($composites as $composite) {
            foreach ($finder->findInstanceOf($composite, Expr::class) as $inner) {
                if ($inner !== $composite) {
                    $nested[spl_object_id($inner)] = true;
                }
            }
        }

        /** @var list<Expr> $strings */
        $strings = [...$composites, ...$finder->findInstanceOf($class, String_::class)];

        return array_values(array_filter(
            $strings,
            static fn (Expr $string): bool => ! isset($nested[spl_object_id($string)]),
        ));
    }

    /**
     * An ALTER whose target collapsed to nothing once interpolation was resolved —
     * the table is named by something this rule cannot read, so the statement is
     * neither cleared nor flagged on its merits. Reported rather than passed over:
     * silence and "checked, safe" must not look the same.
     */
    private function targetIsUnreadable(string $sql): bool
    {
        if (preg_match('/ALTER\s+TABLE\s+(?:IF\s+EXISTS\s+)?(?:`?[\w$]+`?\s*\.\s*)?`?([\w$]*)/i', $sql, $matches) !== 1) {
            return true;
        }

        return $matches[1] === '';
    }

    /**
     * The outlier a statement ALTERs, keyed on the target of the statement rather
     * than any mention of the name. A new table may point a foreign key AT an
     * outlier — `ALTER TABLE lti_grades ADD CONSTRAINT ... REFERENCES video_sessions`
     * costs nothing on an empty table, and a checker that rejects it is a checker
     * someone switches off.
     */
    private function outlierAlteredBy(string $sql): ?string
    {
        foreach ($this->outlierTables as $table) {
            $target = '/ALTER\s+TABLE\s+(?:`?[\w$]+`?\s*\.\s*)?`?' . preg_quote($table, '/') . '`?(?![\w$])/i';

            if (preg_match($target, $sql) === 1) {
                return $table;
            }
        }

        return null;
    }
}
