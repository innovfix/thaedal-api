<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('name_tamil', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->unsignedInteger('duration_days');
            $table->enum('duration_type', ['monthly', 'quarterly', 'yearly', 'lifetime']);
            $table->unsignedTinyInteger('trial_days')->default(0);
            $table->json('features')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->unsignedTinyInteger('discount_percentage')->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};

