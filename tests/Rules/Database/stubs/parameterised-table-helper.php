<?php declare(strict_types=1);

use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumn('video_sessions', 'foo', fn (Blueprint $table) => $table->string('foo')->nullable());
        $this->addColumn('videos', 'bar', fn (Blueprint $table) => $table->string('bar')->nullable());
    }

    private function addColumn(string $table, string $column, Closure $definition): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, $definition);
    }
};
