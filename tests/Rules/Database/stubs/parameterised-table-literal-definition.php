<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addLocale('video_sessions');
        $this->addLocale('videos');
    }

    private function addLocale(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('locale', 8)->nullable();
        });
    }
};
