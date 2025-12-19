<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('video_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['like', 'dislike', 'save']);
            $table->timestamps();

            $table->unique(['user_id', 'video_id', 'type']);
            $table->index(['video_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_interactions');
    }
};

