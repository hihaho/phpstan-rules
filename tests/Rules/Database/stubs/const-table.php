<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const string TABLE = 'video_sessions';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index('foo', 'foo_index');
        });
    }
};
