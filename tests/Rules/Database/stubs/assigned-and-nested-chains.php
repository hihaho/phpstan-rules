<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'video_sessions';

    private bool $withLocale = true;

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table): void {
            $column = $table->string('external_learner_id', 255);
            $column->nullable();

            if ($this->withLocale) {
                $table->index('locale');
            }
        });
    }
};
