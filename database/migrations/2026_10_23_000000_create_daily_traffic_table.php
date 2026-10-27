<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_traffic', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_traffic');
    }
};
