<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_history', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('video_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('watched_duration')->default(0); // in seconds
            $table->unsignedTinyInteger('progress_percentage')->default(0); // 0-100
            $table->boolean('completed')->default(false);
            $table->timestamp('last_watched_at');
            $table->timestamps();

            $table->unique(['user_id', 'video_id']);
            $table->index(['user_id', 'last_watched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_history');
    }
};

