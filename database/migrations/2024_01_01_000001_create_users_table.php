<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone_number', 20)->unique();
            $table->string('name', 100)->nullable();
            $table->string('email', 150)->nullable()->unique();
            $table->string('avatar_url', 500)->nullable();
            $table->boolean('is_subscribed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone_number');
            $table->index('is_subscribed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

