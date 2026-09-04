<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'video_sessions';

    /** @var array<string, callable> */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            'external_learner_id' => fn (Blueprint $table) => $table->string('external_learner_id', 255)->instant(),
            'locale' => $this->localeColumn(),
        ];
    }

    public function up(): void
    {
        foreach ($this->columns as $column => $definition) {
            Schema::table($this->table, $definition);
        }
    }

    private function localeColumn(): callable
    {
        return fn (Blueprint $table) => $table->string('locale', 8);
    }
};
