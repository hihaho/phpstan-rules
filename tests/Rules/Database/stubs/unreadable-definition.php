<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'video_sessions';

    public function up(): void
    {
        Schema::table($this->table, $this->definition());
    }

    private function definition(): callable
    {
        return require __DIR__ . '/definition.php';
    }
};
