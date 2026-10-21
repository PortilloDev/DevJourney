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
            $table->tinyInteger('rating')->nullable()->after('refused_stage');
            $table->decimal('cost_eur', 10, 6)->nullable()->after('tokens_out');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->dropColumn(['rating', 'cost_eur']);
        });
    }
};
