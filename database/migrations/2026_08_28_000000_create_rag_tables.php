<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('pages')->nullable();
            $table->string('status')->default('pending'); // pending, indexed, failed
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->string('model', 100);
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedInteger('context_chunks')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->text('sources')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('documents');
    }
};
