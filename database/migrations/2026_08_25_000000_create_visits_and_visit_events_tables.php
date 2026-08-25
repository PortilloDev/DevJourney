<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_token')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 64)->nullable();
            $table->string('country', 2)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device', 20)->nullable();
            $table->text('referer')->nullable();
            $table->string('entry_url', 1000)->nullable();
            $table->string('entry_path', 500)->nullable();
            $table->string('entry_page_type', 50)->nullable();
            $table->unsignedInteger('page_views')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['visitor_token', 'last_activity_at']);
            $table->index('started_at');
        });

        Schema::create('visit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('page_view')->index();
            $table->string('page_type', 50)->nullable()->index();
            $table->string('path', 500)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('reference', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index('visit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_events');
        Schema::dropIfExists('visits');
    }
};
