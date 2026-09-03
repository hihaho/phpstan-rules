<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'video_sessions';

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table): void {
            $table->string('external_learner_id', 255)->nullable()->instant();
        });
    }

    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table): void {
            $table->dropColumn('external_learner_id')->instant();
        });
    }
};
