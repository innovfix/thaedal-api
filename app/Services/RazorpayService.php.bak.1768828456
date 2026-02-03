<?php

namespace App\Services;

use Razorpay\Api\Api;
use Illuminate\Support\Facades\Log;

class RazorpayService
{
    protected Api $api;
    protected string $keyId;
    protected string $keySecret;

    public function __construct()
    {
        $this->keyId = config('services.razorpay.key_id');
        $this->keySecret = config('services.razorpay.key_secret');
        $this->api = new Api($this->keyId, $this->keySecret);
    }

    /**
     * Create a Razorpay order
     */
    public function createOrder(float $amount, string $receipt, array $notes = []): array
    {
        try {
            $order = $this->api->order->create([
                'amount' => (int) ($amount * 100), // Convert to paise
                'currency' => 'INR',
                'receipt' => $receipt,
                'notes' => $notes,
                'payment_capture' => 1, // Auto capture
            ]);

            Log::info("Razorpay order created: {$order->id}");

            return [
                'id' => $order->id,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'receipt' => $order->receipt,
                'status' => $order->status,
            ];
        } catch (\Exception $e) {
            Log::error("Razorpay order creation failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify payment signature
     */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        try {
            $expectedSignature = hash_hmac(
                'sha256',
                $orderId . '|' . $paymentId,
                $this->keySecret
            );

            return hash_equals($expectedSignature, $signature);
        } catch (\Exception $e) {
            Log::error("Razorpay signature verification failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch payment details
     */
    public function fetchPayment(string $paymentId): array
    {
        try {
            $payment = $this->api->payment->fetch($paymentId);

            return [
                'id' => $payment->id,
                'amount' => $payment->amount / 100,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'method' => $payment->method,
                'email' => $payment->email,
                'contact' => $payment->contact,
                'created_at' => $payment->created_at,
            ];
        } catch (\Exception $e) {
            Log::error("Razorpay fetch payment failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a refund
     */
    public function refund(string $paymentId, float $amount = null, array $notes = []): array
    {
        try {
            $params = ['notes' => $notes];
            
            if ($amount) {
                $params['amount'] = (int) ($amount * 100);
            }

            $refund = $this->api->refund->create([
                'payment_id' => $paymentId,
                ...$params,
            ]);

            Log::info("Razorpay refund created: {$refund->id}");

            return [
                'id' => $refund->id,
                'amount' => $refund->amount / 100,
                'status' => $refund->status,
            ];
        } catch (\Exception $e) {
            Log::error("Razorpay refund failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get Razorpay key ID (for frontend)
     */
    public function getKeyId(): string
    {
        return $this->keyId;
    }
}

