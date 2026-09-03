<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE `lti_grades` ADD CONSTRAINT `lti_grades_video_session_id_foreign` FOREIGN KEY (`video_session_id`) REFERENCES `video_sessions` (`id`)');
    }
};
