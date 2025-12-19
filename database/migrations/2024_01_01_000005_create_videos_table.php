<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('thumbnail_url', 500);
            $table->string('video_url', 500);
            $table->string('video_type', 20)->default('youtube'); // youtube, hosted, vimeo
            $table->unsignedInteger('duration')->default(0); // in seconds
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('dislikes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('saves_count')->default(0);
            $table->foreignUuid('category_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('creator_id')->constrained()->onDelete('cascade');
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->json('tags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_published', 'published_at']);
            $table->index(['creator_id', 'is_published']);
            $table->index(['is_published', 'published_at']);
            $table->index(['is_featured', 'is_published']);
            $table->index('views_count');
            $table->fullText(['title', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};

