<?php declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class NotAMigration
{
    private string $table = 'video_sessions';

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table): void {
            $table->index('foo');
        });
    }
}
