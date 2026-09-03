<?php declare(strict_types=1);

use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'video_sessions';

    public function up(): void
    {
        Schema::create('lti_grades', function (Blueprint $table): void {
            $table->index('video_session_id');
        });

        $this->addIndex(fn (Blueprint $table) => $table->index('external_learner_id'));
    }

    private function addIndex(Closure $definition): void
    {
        Schema::table($this->table, $definition);
    }
};
