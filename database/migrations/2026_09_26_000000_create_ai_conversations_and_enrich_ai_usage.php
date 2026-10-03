<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('ip_address', 64)->nullable()->index();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->unsignedInteger('turns_count')->default(0);
            $table->unsignedInteger('tokens_total')->default(0);
            $table->timestamps();
        });

        Schema::table('ai_usage', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('id')
                ->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->after('conversation_id')
                ->constrained()->nullOnDelete();
            $table->longText('prompt')->nullable()->after('question');
            $table->longText('answer')->nullable()->after('prompt');
            $table->boolean('refused')->default(false)->after('answer');
            $table->string('refused_stage', 20)->nullable()->after('refused');
            $table->string('provider', 50)->nullable()->after('model');
            $table->string('embedding_model', 100)->nullable()->after('provider');
            $table->unsignedTinyInteger('top_k')->nullable()->after('embedding_model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropConstrainedForeignId('post_id');
            $table->dropColumn([
                'prompt', 'answer', 'refused', 'refused_stage',
                'provider', 'embedding_model', 'top_k',
            ]);
        });

        Schema::dropIfExists('ai_conversations');
    }
};
