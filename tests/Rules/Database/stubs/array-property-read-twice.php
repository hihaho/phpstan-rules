<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'video_sessions';

    /** @var array<string, Closure> */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            'external_learner_id' => fn (Blueprint $table) => $table->string('external_learner_id', 255)->nullable(),
        ];
    }

    public function up(): void
    {
        foreach ($this->columns as $column => $definition) {
            if (Schema::hasColumn($this->table, $column)) {
                continue;
            }

            Schema::table($this->table, $definition);
        }
    }

    public function down(): void
    {
        $this->log(count($this->columns));

        Schema::table($this->table, function (Blueprint $table): void {
            $table->dropColumn(array_reverse(array_keys($this->columns)))->instant();
        });
    }

    private function log(int $columns): void
    {
        //
    }
};
