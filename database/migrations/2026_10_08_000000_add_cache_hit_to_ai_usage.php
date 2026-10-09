<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->boolean('cache_hit')->default(false)->after('refused');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->dropColumn('cache_hit');
        });
    }
};
