<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('difficulty');               // beginner–expert
            $table->string('topic');                    // system-design, ddd, etc.
            $table->longText('question_md');
            $table->longText('question_html');
            $table->longText('answer_md');
            $table->longText('answer_html');
            $table->longText('explanation_md')->nullable();
            $table->longText('explanation_html')->nullable();
            $table->string('english_level', 2);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'topic', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
