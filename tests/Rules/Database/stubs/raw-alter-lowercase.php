<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('alter table `video_sessions` add index `foo` (`bar`), algorithm=copy');
    }
};
