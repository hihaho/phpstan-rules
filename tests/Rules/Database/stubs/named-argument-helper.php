<?php declare(strict_types=1);

use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'video_sessions';

    public function up(): void
    {
        $this->addIndex(definition: fn (Blueprint $table) => $table->index('external_learner_id'), index: 'learner_index');
    }

    private function addIndex(string $index, Closure $definition): void
    {
        if (Schema::hasIndex($this->table, $index)) {
            return;
        }

        Schema::table($this->table, $definition);
    }
};
