<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['card', 'upi', 'netbanking', 'wallet']);
            $table->boolean('is_default')->default(false);
            $table->json('details'); // Encrypted card/UPI details
            $table->string('razorpay_token', 255)->nullable();
            $table->string('last_four', 4)->nullable(); // Last 4 digits for cards
            $table->string('brand', 50)->nullable(); // visa, mastercard, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};

