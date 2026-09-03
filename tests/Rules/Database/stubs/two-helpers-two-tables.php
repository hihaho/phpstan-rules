<?php declare(strict_types=1);

use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $outlier = 'video_sessions';

    private string $ordinary = 'questions';

    public function up(): void
    {
        $this->addOutlierIndex(fn (Blueprint $table) => $table->index('external_learner_id'));
        $this->addOrdinaryIndex(fn (Blueprint $table) => $table->index('question_group_id'));
    }

    private function addOutlierIndex(Closure $definition): void
    {
        Schema::table($this->outlier, $definition);
    }

    private function addOrdinaryIndex(Closure $definition): void
    {
        Schema::table($this->ordinary, $definition);
    }
};
