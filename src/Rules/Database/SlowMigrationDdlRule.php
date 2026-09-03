<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Database;

use Illuminate\Database\Migrations\Migration;
use Override;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Flags DDL a migration cannot run instantly against one of the configured outlier
 * tables.
 *
 * HPB-5876: a migration added a foreign key to `video_sessions` (74.6M rows) inside
 * the Vapor deploy hook. MySQL rebuilt the table under ALGORITHM=COPY and held an
 * exclusive metadata lock for 47 minutes, queueing every session write behind it
 * until the connection pool exhausted. The deploy hook is capped at 120s and Lambda
 * retries an async invocation twice, so one redeploy stacked six ALTERs on the
 * hottest table in the system.
 *
 * On an outlier table a migration may only do work MySQL is told to do instantly.
 * Index builds, foreign keys and column repositioning move out of the deploy path
 * into the Nova action + queued job pattern.
 *
 * Five shapes are reported:
 *
 *   Slow op      Schema::table('video_sessions', fn ($t) => $t->index('foo'))
 *   Unasserted   $table->string('foo')->nullable()      // no ->instant()
 *   Raw SQL      DB::statement('ALTER TABLE `video_sessions` ADD INDEX ...')
 *   Destructive  Schema::rename('video_sessions', ...)
 *   Unreadable   an ALTER whose target this rule cannot resolve to a literal
 *
 * Creation calls only. `dropIndex()`/`dropForeign()` live in `down()`, which a deploy
 * never runs; flagging them pushes authors to write migrations that cannot roll back.
 *
 * Runs on the class node rather than per call because the table name is usually held
 * in a typed property, where PHPStan infers `string` and the literal is lost. The
 * literal is read off the class declaration instead — see MigrationTableNameResolver.
 *
 * Inert until `outlierTables` is configured.
 *
 * @implements Rule<InClassNode>
 */
final readonly class SlowMigrationDdlRule implements Rule
{
    /**
     * Creation calls only, and every one of them rebuilds or locks the table on a
     * row count this large.
     */
    private const array SLOW_OPERATIONS = [
        'index',
        'unique',
        'fullText',
        'spatialIndex',
        'foreign',
        'constrained',
        'after',
        'change',
    ];

    /**
     * Column helpers that return void and so cannot carry ->instant(). There is no
     * safe way to write them against a table this size: the columns have to be added
     * individually with the assertion.
     */
    private const array UNASSERTABLE_OPERATIONS = [
        'timestamps',
        'timestampsTz',
        'nullableTimestamps',
        'softDeletes',
        'softDeletesTz',
        'morphs',
        'nullableMorphs',
        'numericMorphs',
        'nullableNumericMorphs',
        'uuidMorphs',
        'nullableUuidMorphs',
        'ulidMorphs',
        'nullableUlidMorphs',
    ];

    private const array DESTRUCTIVE_SCHEMA_CALLS = [
        'rename',
        'drop',
        'dropIfExists',
    ];

    private const string TIP = 'Assert the algorithm on column work ($table->string(\'foo\')->instant()), or move index and foreign-key work to a queued job. Reference: .ai/docs/database-scale-and-ddl.md, incident HPB-5876.';

    /**
     * @param  list<string>  $outlierTables  tables whose row count makes a rebuild a
     *                                       production incident. Empty by default —
     *                                       each consuming project measures its own.
     */
    public function __construct(
        private array $outlierTables,
        private BlueprintChain $chain,
        private RawAlterScanner $scanner,
        private BlueprintDefinitionResolver $definitions,
    ) {}

    #[Override]
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param  InClassNode  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->outlierTables === []) {
            return [];
        }

        if (! $this->isMigration($node->getClassReflection())) {
            return [];
        }

        $class = $node->getOriginalNode();

        if (! $class instanceof Class_) {
            return [];
        }

        $resolver = new MigrationTableNameResolver($class);

        $found = [
            ...$this->inspectSchemaCalls($class, $resolver),
            ...$this->rawAlterFindings($class, $resolver),
        ];

        usort($found, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_map(static fn (array $found): IdentifierRuleError => $found[1], $found);
    }

    /**
     * @return list<array{int, IdentifierRuleError}>
     */
    private function inspectSchemaCalls(Class_ $class, MigrationTableNameResolver $resolver): array
    {
        $errors = [];

        foreach ($this->findNodes($class, StaticCall::class) as $call) {
            if (! $this->isFacadeCall($call, 'Schema')) {
                continue;
            }

            if (! $call->name instanceof Identifier) {
                continue;
            }

            $table = $this->resolveOutlierArgument($call, $resolver);

            if ($table === null) {
                continue;
            }

            $method = $call->name->toString();

            if (in_array($method, self::DESTRUCTIVE_SCHEMA_CALLS, true)) {
                $errors[] = $this->finding(
                    "Schema::{$method}() on `{$table}` rebuilds or destroys a table too large to alter inside a deploy.",
                    'hihaho.database.outlierTableDestructiveSchemaCall',
                    $call->getStartLine(),
                );

                continue;
            }

            if ($method === 'table') {
                $errors = [...$errors, ...$this->inspectBlueprintClosure($class, $call, $table)];
            }
        }

        return $errors;
    }

    /**
     * @return list<array{int, IdentifierRuleError}>
     */
    private function inspectBlueprintClosure(Class_ $class, StaticCall $call, string $table): array
    {
        $closures = $this->definitions->forCall($class, $call);

        if ($closures === []) {
            return [$this->finding(
                "Schema::table() on `{$table}` whose definition cannot be read statically, so the operations it runs cannot be checked. Pass the closure at the call site.",
                'hihaho.database.uncheckableSchemaChange',
                $call->getStartLine(),
            )];
        }

        $errors = [];

        foreach ($closures as $closure) {
            $errors = [...$errors, ...$this->inspectDefinition($closure, $table)];
        }

        return $errors;
    }

    /**
     * @return list<array{int, IdentifierRuleError}>
     */
    private function inspectDefinition(Closure|ArrowFunction $closure, string $table): array
    {
        $errors = [];

        foreach ($this->chain->rootedChains($closure) as $statement) {
            $finding = $this->inspectBlueprintStatement($statement, $table);

            if ($finding !== null) {
                $errors[] = $finding;
            }
        }

        return $errors;
    }

    /**
     * @return array{int, IdentifierRuleError}|null
     */
    private function inspectBlueprintStatement(MethodCall $statement, string $table): ?array
    {
        $names = $this->chain->methodNames($statement);
        $slow = array_values(array_intersect($names, self::SLOW_OPERATIONS));

        if ($slow !== []) {
            return $this->finding(
                "->{$slow[0]}() on `{$table}` rebuilds the table under ALGORITHM=COPY and holds an exclusive metadata lock for the duration.",
                'hihaho.database.slowMigrationDdl',
                $statement->getStartLine(),
            );
        }

        $unassertable = array_values(array_intersect($names, self::UNASSERTABLE_OPERATIONS));

        if ($unassertable !== []) {
            return $this->finding(
                "->{$unassertable[0]}() on `{$table}` returns void and cannot assert ->instant(), so MySQL is free to rebuild the table. Add the columns individually with the assertion.",
                'hihaho.database.slowMigrationDdl',
                $statement->getStartLine(),
            );
        }

        if ($this->chain->addsOrDropsColumn($names) && ! in_array('instant', $names, true)) {
            return $this->finding(
                "Column work on `{$table}` without ->instant(). Unasserted, MySQL is free to rebuild the table instead of failing fast.",
                'hihaho.database.migrationColumnWithoutInstant',
                $statement->getStartLine(),
            );
        }

        return null;
    }

    /**
     * @return list<array{int, IdentifierRuleError}>
     */
    private function rawAlterFindings(Class_ $class, MigrationTableNameResolver $resolver): array
    {
        $errors = [];

        foreach ($this->scanner->scan($class, $resolver) as [$line, $table]) {
            $errors[] = $table === null
                ? $this->finding(
                    'Raw ALTER TABLE whose target table cannot be read statically, so this migration cannot be checked against the outlier tables. Name the table with a literal, or assert ALGORITHM=INSTANT.',
                    'hihaho.database.unresolvableAlterTarget',
                    $line,
                )
                : $this->finding(
                    "Raw ALTER TABLE on `{$table}` without ALGORITHM=INSTANT. MySQL picks COPY and rebuilds the table.",
                    'hihaho.database.rawAlterWithoutInstant',
                    $line,
                );
        }

        return $errors;
    }

    private function resolveOutlierArgument(StaticCall $call, MigrationTableNameResolver $resolver): ?string
    {
        $argument = $call->args[0] ?? null;

        if (! $argument instanceof Arg) {
            return null;
        }

        $table = $resolver->resolve($argument->value);

        return $table !== null && in_array($table, $this->outlierTables, true) ? $table : null;
    }

    /**
     * Migrations live in the global namespace and reference the facade both imported
     * and unimported, so the last segment of the name is what identifies it.
     */
    private function isFacadeCall(StaticCall $call, string $facade): bool
    {
        return $call->class instanceof Name && $call->class->getLast() === $facade;
    }

    private function isMigration(ClassReflection $classReflection): bool
    {
        return (new ObjectType(Migration::class))
            ->isSuperTypeOf(new ObjectType($classReflection->getName()))
            ->yes();
    }

    /**
     * @template TNode of Node
     *
     * @param  class-string<TNode>  $type
     * @return list<TNode>
     */
    private function findNodes(Node $node, string $type): array
    {
        /** @var list<TNode> */
        return (new NodeFinder())->findInstanceOf($node, $type);
    }

    /**
     * @return array{int, IdentifierRuleError} the line is carried alongside so findings
     *                                         from separate passes can be ordered
     */
    private function finding(string $message, string $identifier, int $line): array
    {
        return [$line, RuleErrorBuilder::message($message)
            ->identifier($identifier)
            ->tip(self::TIP)
            ->line($line)
            ->build()];
    }
}
