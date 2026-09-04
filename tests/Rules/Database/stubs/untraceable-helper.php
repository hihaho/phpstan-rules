<?php declare(strict_types=1);

use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function apply(Closure $definition): void
    {
        Schema::table('video_sessions', $definition);
    }
};
