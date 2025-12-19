<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('subscription_id')->nullable()->constrained()->onDelete('set null');
            $table->string('order_id', 50)->unique();
            $table->string('razorpay_order_id', 50)->nullable();
            $table->string('razorpay_payment_id', 50)->nullable();
            $table->string('razorpay_signature', 255)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'refunded', 'cancelled']);
            $table->string('payment_method', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('invoice_url', 500)->nullable();
            $table->string('receipt_number', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index('razorpay_order_id');
            $table->index('razorpay_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

