<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'video_sessions';

    /** @var array<string, Closure> */
    private array $columns = [];

    public function up(): void
    {
        foreach ($this->columns as $column => $definition) {
            Schema::table($this->table, $definition);
        }
    }
};
