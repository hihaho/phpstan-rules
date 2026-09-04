<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'video_sessions';

    /** @var array<int, Closure> */
    private array $columns = [];

    public function __construct()
    {
        array_push($this->columns, fn (Blueprint $table) => $table->string('locale', 8));
    }

    public function up(): void
    {
        foreach ($this->columns as $definition) {
            Schema::table($this->table, $definition);
        }
    }
};
