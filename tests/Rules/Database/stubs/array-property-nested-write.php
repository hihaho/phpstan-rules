<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'video_sessions';

    /** @var array<string, array<string, Closure>> */
    private array $columns = [
        'learner' => [],
    ];

    public function __construct()
    {
        $this->columns['learner']['external_learner_id'] = fn (Blueprint $table) => $table->string('external_learner_id', 255);
    }

    public function up(): void
    {
        foreach ($this->columns['learner'] as $column => $definition) {
            Schema::table($this->table, $definition);
        }
    }
};
