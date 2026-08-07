<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('achieved_at');
            $table->string('icon')->nullable();
            $table->string('type');                     // english, technical, career, project
            $table->timestamps();

            $table->index('achieved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
