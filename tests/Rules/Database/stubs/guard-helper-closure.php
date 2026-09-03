<?php declare(strict_types=1);

use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const string LEARNER_ID_INDEX = 'video_sessions_external_learner_id_index';

    private string $table = 'video_sessions';

    public function up(): void
    {
        $this->addColumn('external_learner_id', fn (Blueprint $table) => $table->string('external_learner_id', 255)->nullable());
        $this->addIndex(self::LEARNER_ID_INDEX, fn (Blueprint $table) => $table->index('external_learner_id', self::LEARNER_ID_INDEX));
    }

    private function addColumn(string $column, Closure $definition): void
    {
        if (Schema::hasColumn($this->table, $column)) {
            return;
        }

        Schema::table($this->table, $definition);
    }

    private function addIndex(string $index, Closure $definition): void
    {
        if (Schema::hasIndex($this->table, $index)) {
            return;
        }

        Schema::table($this->table, $definition);
    }
};
