<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lti_grades', function (Blueprint $table): void {
            $table->foreign('video_session_id')->references('id')->on('video_sessions');
        });
    }
};
