<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private string $table = 'video_sessions';

    public function up(): void
    {
        DB::statement("ALTER TABLE `{$this->table}` ADD COLUMN `foo` BIGINT UNSIGNED NULL");
    }
};
