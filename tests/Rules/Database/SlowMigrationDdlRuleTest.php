<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Database;

use Hihaho\PhpstanRules\Rules\Database\ArrayDefinitionReader;
use Hihaho\PhpstanRules\Rules\Database\BlueprintChain;
use Hihaho\PhpstanRules\Rules\Database\BlueprintDefinitionResolver;
use Hihaho\PhpstanRules\Rules\Database\HelperCallSites;
use Hihaho\PhpstanRules\Rules\Database\PropertyArraySource;
use Hihaho\PhpstanRules\Rules\Database\RawAlterScanner;
use Hihaho\PhpstanRules\Rules\Database\SlowMigrationDdlRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The cases below are ported from `tools/verify/migration-safety.test.sh` in
 * hihaho/hihaho, the shell gate this rule replaces. They are that gate's accumulated
 * bug history — both incident shapes, the interpolated raw ALTER, the class-constant
 * table name, lowercase SQL and the multi-line chains — not a wishlist.
 *
 * @extends RuleTestCase<SlowMigrationDdlRule>
 */
final class SlowMigrationDdlRuleTest extends RuleTestCase
{
    /** @var list<string> */
    private const array OUTLIER_TABLES = ['video_sessions', 'video_session_questions', 'video_session_answers'];

    private const string TIP = 'Assert the algorithm on column work ($table->string(\'foo\')->instant()), or move index and foreign-key work to a queued job. Reference: .ai/docs/database-scale-and-ddl.md, incident HPB-5876.';

    #[Override]
    protected function getRule(): Rule
    {
        $chain = new BlueprintChain(self::createReflectionProvider());

        return new SlowMigrationDdlRule(
            self::OUTLIER_TABLES,
            $chain,
            new RawAlterScanner(self::OUTLIER_TABLES),
            new BlueprintDefinitionResolver(new ArrayDefinitionReader(new PropertyArraySource()), new HelperCallSites()),
        );
    }

    #[Test]
    public function flags_a_foreign_key_added_to_an_outlier_table(): void
    {
        $this->analyse([__DIR__ . '/stubs/incident-foreign-key.php'], [
            ['->constrained() on `video_sessions` rebuilds the table under ALGORITHM=COPY and holds an exclusive metadata lock for the duration.', 13, self::TIP],
        ]);
    }

    #[Test]
    public function flags_an_index_built_on_an_outlier_table(): void
    {
        $this->analyse([__DIR__ . '/stubs/incident-index.php'], [
            ['->index() on `video_sessions` rebuilds the table under ALGORITHM=COPY and holds an exclusive metadata lock for the duration.', 13, self::TIP],
        ]);
    }

    #[Test]
    public function flags_a_column_added_without_instant(): void
    {
        $this->analyse([__DIR__ . '/stubs/column-unasserted.php'], [
            ['Column work on `video_sessions` without ->instant(). Unasserted, MySQL is free to rebuild the table instead of failing fast.', 13, self::TIP],
        ]);
    }

    #[Test]
    public function allows_column_work_that_asserts_instant(): void
    {
        $this->analyse([__DIR__ . '/stubs/column-instant.php'], []);
    }

    #[Test]
    public function allows_a_new_table_to_reference_an_outlier_table(): void
    {
        $this->analyse([__DIR__ . '/stubs/create-referencing-outlier.php'], []);
    }

    #[Test]
    public function allows_slow_ddl_on_an_ordinary_table(): void
    {
        $this->analyse([__DIR__ . '/stubs/ordinary-table.php'], []);
    }

    #[Test]
    public function flags_a_raw_alter_on_an_outlier_table(): void
    {
        $this->analyse([__DIR__ . '/stubs/raw-alter.php'], [
            ['Raw ALTER TABLE on `video_sessions` without ALGORITHM=INSTANT. MySQL picks COPY and rebuilds the table.', 9, self::TIP],
        ]);
    }

    #[Test]
    public function allows_a_raw_alter_that_asserts_instant(): void
    {
        $this->analyse([__DIR__ . '/stubs/raw-alter-instant.php'], []);
    }

    #[Test]
    public function flags_a_raw_alter_asserting_only_inplace(): void
    {
        $this->analyse([__DIR__ . '/stubs/raw-alter-inplace.php'], [
            ['Raw ALTER TABLE on `video_sessions` without ALGORITHM=INSTANT. MySQL picks COPY and rebuilds the table.', 9, self::TIP],
        ]);
    }

    #[Test]
    public function flags_a_rename_of_an_outlier_table(): void
    {
        $this->analyse([__DIR__ . '/stubs/rename-outlier.php'], [
            ['Schema::rename() on `video_sessions` rebuilds or destroys a table too large to alter inside a deploy.', 9, self::TIP],
        ]);
    }

    #[Test]
    public function flags_a_raw_alter_whose_table_name_is_interpolated(): void
    {
        $this->analyse([__DIR__ . '/stubs/raw-alter-interpolated.php'], [
            ['Raw ALTER TABLE on `video_sessions` without ALGORITHM=INSTANT. MySQL picks COPY and rebuilds the table.', 11, self::TIP],
        ]);
    }

    #[Test]
    public function flags_slow_ddl_when_the_table_is_named_by_a_class_constant(): void
    {
        $this->analyse([__DIR__ . '/stubs/const-table.php'], [
            ['->index() on `video_sessions` rebuilds the table under ALGORITHM=COPY and holds an exclusive metadata lock for the duration.', 13, self::TIP],
        ]);
    }

    #[Test]
    public function flags_a_lowercase_raw_alter(): void
    {
        $this->analyse([__DIR__ . '/stubs/raw-alter-lowercase.php'], [
            ['Raw ALTER TABLE on `video_sessions` without ALGORITHM=INSTANT. MySQL picks COPY and rebuilds the table.', 9, self::TIP],
        ]);
    }

    #[Test]
    public function allows_a_multi_line_chain_carrying_instant(): void
    {
        $this->analyse([__DIR__ . '/stubs/multiline-instant.php'], []);
    }

    #[Test]
    public function flags_a_multi_line_chain_missing_instant(): void
    {
        $this->analyse([__DIR__ . '/stubs/multiline-unasserted.php'], [
            ['Column work on `video_sessions` without ->instant(). Unasserted, MySQL is free to rebuild the table instead of failing fast.', 13, self::TIP],
        ]);
    }

    #[Test]
    public function allows_a_raw_alter_that_only_references_an_outlier_table(): void
    {
        $this->analyse([__DIR__ . '/stubs/raw-alter-referencing-outlier.php'], []);
    }

    #[Test]
    public function flags_column_helpers_that_cannot_assert_instant(): void
    {
        $this->analyse([__DIR__ . '/stubs/unassertable-column-helpers.php'], [
            ['->timestamps() on `video_sessions` returns void and cannot assert ->instant(), so MySQL is free to rebuild the table. Add the columns individually with the assertion.', 13, self::TIP],
            ['->softDeletes() on `video_sessions` returns void and cannot assert ->instant(), so MySQL is free to rebuild the table. Add the columns individually with the assertion.', 14, self::TIP],
            ['->nullableMorphs() on `video_sessions` returns void and cannot assert ->instant(), so MySQL is free to rebuild the table. Add the columns individually with the assertion.', 15, self::TIP],
        ]);
    }

    #[Test]
    public function flags_chains_assigned_to_a_variable_and_nested_in_a_conditional(): void
    {
        $this->analyse([__DIR__ . '/stubs/assigned-and-nested-chains.php'], [
            ['Column work on `video_sessions` without ->instant(). Unasserted, MySQL is free to rebuild the table instead of failing fast.', 15, self::TIP],
            ['->index() on `video_sessions` rebuilds the table under ALGORITHM=COPY and holds an exclusive metadata lock for the duration.', 19, self::TIP],
        ]);
    }

    #[Test]
    public function reports_a_raw_alter_whose_target_cannot_be_read(): void
    {
        $this->analyse([__DIR__ . '/stubs/unresolvable-alter-target.php'], [
            ['Raw ALTER TABLE whose target table cannot be read statically, so this migration cannot be checked against the outlier tables. Name the table with a literal, or assert ALGORITHM=INSTANT.', 13, self::TIP],
        ]);
    }

    #[Test]
    public function flags_slow_ddl_inside_an_arrow_function_closure(): void
    {
        $this->analyse([__DIR__ . '/stubs/arrow-function-closure.php'], [
            ['->index() on `video_session_answers` rebuilds the table under ALGORITHM=COPY and holds an exclusive metadata lock for the duration.', 10, self::TIP],
        ]);
    }

    #[Test]
    public function flags_a_drop_of_an_outlier_table(): void
    {
        $this->analyse([__DIR__ . '/stubs/drop-outlier.php'], [
            ['Schema::dropIfExists() on `video_session_questions` rebuilds or destroys a table too large to alter inside a deploy.', 9, self::TIP],
        ]);
    }

    /**
     * The shape the incident remediation itself uses: every statement is guarded so a
     * run killed by the deploy timeout can resume, and the guard is factored into a
     * private helper — which leaves the `Schema::table()` call holding a variable
     * rather than a literal closure.
     */
    #[Test]
    public function flags_definitions_reaching_schema_table_through_a_helper(): void
    {
        $this->analyse([__DIR__ . '/stubs/guard-helper-closure.php'], [
            ['Column work on `video_sessions` without ->instant(). Unasserted, MySQL is free to rebuild the table instead of failing fast.', 15, self::TIP],
            ['->index() on `video_sessions` rebuilds the table under ALGORITHM=COPY and holds an exclusive metadata lock for the duration.', 16, self::TIP],
        ]);
    }

    #[Test]
    public function attributes_a_closure_written_at_its_own_call_to_that_call_only(): void
    {
        $this->analyse([__DIR__ . '/stubs/helper-and-own-call-site.php'], [
            ['->index() on `video_sessions` rebuilds the table under ALGORITHM=COPY and holds an exclusive metadata lock for the duration.', 17, self::TIP],
        ]);
    }

    /**
     * Each helper's definitions are traced back to its own call sites, so work on an
     * ordinary table is not attributed to the outlier the neighbouring helper alters.
     */
    #[Test]
    public function keeps_two_helpers_altering_two_tables_apart(): void
    {
        $this->analyse([__DIR__ . '/stubs/two-helpers-two-tables.php'], [
            ['->index() on `video_sessions` rebuilds the table under ALGORITHM=COPY and holds an exclusive metadata lock for the duration.', 15, self::TIP],
        ]);
    }

    #[Test]
    public function traces_a_definition_passed_as_a_named_argument(): void
    {
        $this->analyse([__DIR__ . '/stubs/named-argument-helper.php'], [
            ['->index() on `video_sessions` rebuilds the table under ALGORITHM=COPY and holds an exclusive metadata lock for the duration.', 13, self::TIP],
        ]);
    }

    #[Test]
    public function reports_a_schema_table_definition_that_cannot_be_read(): void
    {
        $this->analyse([__DIR__ . '/stubs/unreadable-definition.php'], [
            ['Schema::table() on `video_sessions` whose definition cannot be read statically, so the operations it runs cannot be checked. Pass the closure at the call site.', 11, self::TIP],
        ]);
    }

    /**
     * The silent case: with the table name a parameter too, nothing about the call
     * says which table it alters. Each call site pairs its own table with its own
     * definition, so the outlier is reported and the ordinary table is not.
     */
    #[Test]
    public function pairs_a_parameterised_table_with_its_own_definition(): void
    {
        $this->analyse([__DIR__ . '/stubs/parameterised-table-helper.php'], [
            ['Column work on `video_sessions` without ->instant(). Unasserted, MySQL is free to rebuild the table instead of failing fast.', 12, self::TIP],
        ]);
    }

    #[Test]
    public function reads_definitions_held_in_an_array_property(): void
    {
        $this->analyse([__DIR__ . '/stubs/array-property-definitions.php'], [
            ['Column work on `video_sessions` without ->instant(). Unasserted, MySQL is free to rebuild the table instead of failing fast.', 18, self::TIP],
        ]);
    }

    #[Test]
    public function refuses_an_array_property_written_more_than_once(): void
    {
        $this->analyse([__DIR__ . '/stubs/array-property-written-twice.php'], [
            ['Schema::table() on `video_sessions` whose definition cannot be read statically, so the operations it runs cannot be checked. Pass the closure at the call site.', 26, self::TIP],
        ]);
    }

    /**
     * Only the table is a parameter here; the definition is written inside the helper
     * and stays readable, once per call site.
     */
    #[Test]
    public function reads_a_literal_definition_inside_a_parameterised_table_helper(): void
    {
        $this->analyse([__DIR__ . '/stubs/parameterised-table-literal-definition.php'], [
            ['Column work on `video_sessions` without ->instant(). Unasserted, MySQL is free to rebuild the table instead of failing fast.', 18, self::TIP],
        ]);
    }

    /**
     * One entry the reader cannot follow makes the whole list unresolved. Reading the
     * rest and reporting on those would leave the skipped entry looking checked.
     */
    #[Test]
    public function refuses_an_array_holding_an_entry_it_cannot_read(): void
    {
        $this->analyse([__DIR__ . '/stubs/array-property-mixed-entries.php'], [
            ['Schema::table() on `video_sessions` whose definition cannot be read statically, so the operations it runs cannot be checked. Pass the closure at the call site.', 25, self::TIP],
        ]);
    }

    #[Test]
    public function refuses_an_array_property_extended_by_a_compound_assignment(): void
    {
        $this->analyse([__DIR__ . '/stubs/array-property-compound-write.php'], [
            ['Schema::table() on `video_sessions` whose definition cannot be read statically, so the operations it runs cannot be checked. Pass the closure at the call site.', 26, self::TIP],
        ]);
    }

    /**
     * A statically empty map runs no iteration, so there is nothing to check — which
     * is not the same answer as a map the rule could not read.
     */
    #[Test]
    public function allows_a_definition_map_that_is_empty(): void
    {
        $this->analyse([__DIR__ . '/stubs/empty-definition-map.php'], []);
    }

    #[Test]
    public function reads_a_table_a_call_site_leaves_to_its_default(): void
    {
        $this->analyse([__DIR__ . '/stubs/defaulted-table-parameter.php'], [
            ['Column work on `video_sessions` without ->instant(). Unasserted, MySQL is free to rebuild the table instead of failing fast.', 17, self::TIP],
        ]);
    }

    #[Test]
    public function refuses_an_array_property_mutated_through_a_call(): void
    {
        $this->analyse([__DIR__ . '/stubs/array-property-mutated-by-call.php'], [
            ['Schema::table() on `video_sessions` whose definition cannot be read statically, so the operations it runs cannot be checked. Pass the closure at the call site.', 22, self::TIP],
        ]);
    }

    /**
     * A helper nothing in the class calls is understood no better than an untraced
     * call, so it is reported rather than passed over for lack of a call site.
     */
    #[Test]
    public function reports_a_helper_with_no_call_site_to_trace(): void
    {
        $this->analyse([__DIR__ . '/stubs/untraceable-helper.php'], [
            ['Schema::table() on `video_sessions` whose definition cannot be read statically, so the operations it runs cannot be checked. Pass the closure at the call site.', 11, self::TIP],
        ]);
    }

    /**
     * A lone compound write adds to whatever the property already held, which may come
     * from somewhere this reader cannot see, so it is not the property's whole value.
     */
    #[Test]
    public function refuses_a_property_filled_only_by_a_compound_write(): void
    {
        $this->analyse([__DIR__ . '/stubs/array-property-only-compound-write.php'], [
            ['Schema::table() on `video_sessions` whose definition cannot be read statically, so the operations it runs cannot be checked. Pass the closure at the call site.', 24, self::TIP],
        ]);
    }

    #[Test]
    public function refuses_a_loop_that_rebinds_its_own_value_variable(): void
    {
        $this->analyse([__DIR__ . '/stubs/rebound-loop-variable.php'], [
            ['Schema::table() on `video_sessions` whose definition cannot be read statically, so the operations it runs cannot be checked. Pass the closure at the call site.', 21, self::TIP],
        ]);
    }

    /**
     * Nested loops may reuse the value variable's name; the definitions come from the
     * nearest binding, not the first one found.
     */
    #[Test]
    public function reads_the_innermost_loop_binding_the_definition(): void
    {
        $this->analyse([__DIR__ . '/stubs/nested-definition-loops.php'], [
            ['Column work on `video_sessions` without ->instant(). Unasserted, MySQL is free to rebuild the table instead of failing fast.', 22, self::TIP],
        ]);
    }

    #[Test]
    public function ignores_a_class_that_is_not_a_migration(): void
    {
        $this->analyse([__DIR__ . '/stubs/not-a-migration.php'], []);
    }
}
