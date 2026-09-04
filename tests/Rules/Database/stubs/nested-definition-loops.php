<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'video_sessions';

    /** @var array<string, Closure> */
    private array $outer = [
        'safe' => 'placeholder',
    ];

    /** @var array<string, Closure> */
    private array $inner;

    public function __construct()
    {
        $this->inner = [
            'locale' => fn (Blueprint $table) => $table->string('locale', 8),
        ];
    }

    public function up(): void
    {
        foreach ($this->outer as $definition) {
            foreach ($this->inner as $definition) {
                Schema::table($this->table, $definition);
            }
        }
    }
};
