<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->text('bio')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->unsignedInteger('subscribers_count')->default(0);
            $table->unsignedBigInteger('total_views')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('social_links')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_verified');
            $table->index('subscribers_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creators');
    }
};

