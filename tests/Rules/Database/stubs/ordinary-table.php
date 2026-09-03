<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'questions';

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table): void {
            $table->foreign('question_group_id')->references('id')->on('question_groups');
            $table->index('question_group_id');
        });
    }
};
