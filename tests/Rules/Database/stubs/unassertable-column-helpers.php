<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'video_sessions';

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table): void {
            $table->timestamps();
            $table->softDeletes();
            $table->nullableMorphs('subject');
        });
    }
};
