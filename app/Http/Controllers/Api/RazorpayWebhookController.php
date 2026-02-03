<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.razorpay.webhook_secret') ?? env('RAZORPAY_WEBHOOK_SECRET');
        if (!$secret) {
            Log::warning('Razorpay webhook received but RAZORPAY_WEBHOOK_SECRET is not set');
            return response()->json(['success' => false, 'message' => 'Webhook not configured'], 500);
        }

        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        if (!$signature) {
            return response()->json(['success' => false, 'message' => 'Missing signature'], 400);
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expected, $signature)) {
            Log::warning('Razorpay webhook signature mismatch');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        $data = $request->json()->all();
        $event = $data['event'] ?? null;

        Log::info('Razorpay webhook received', ['event' => $event]);

        if (!$event) {
            return response()->json(['success' => true]);
        }

        try {
            // Handle payment events
            if (str_starts_with($event, 'payment.')) {
                $entity = Arr::get($data, 'payload.payment.entity');
                if ($entity) {
                    $this->handlePaymentEvent($event, $entity);
                }
            }

            // Handle subscription events
            if (str_starts_with($event, 'subscription.')) {
                $entity = Arr::get($data, 'payload.subscription.entity');
                if ($entity) {
                    $this->handleSubscriptionEvent($event, $entity);
                }
            }

            // Handle refund events
            if (str_starts_with($event, 'refund.')) {
                $entity = Arr::get($data, 'payload.refund.entity');
                if ($entity) {
                    $this->handleRefundEvent($event, $entity);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Razorpay webhook handling failed: ' . $e->getMessage());
            return response()->json(['success' => false], 500);
        }

        return response()->json(['success' => true]);
    }

    private function handlePaymentEvent(string $event, array $paymentEntity): void
    {
        $razorpayPaymentId = $paymentEntity['id'] ?? null;
        $razorpayOrderId = $paymentEntity['order_id'] ?? null;
        $razorpaySubscriptionId = $paymentEntity['subscription_id'] ?? null;
        $status = $paymentEntity['status'] ?? null;
        $method = $paymentEntity['method'] ?? null;
        $amount = $paymentEntity['amount'] ?? null;
        $currency = $paymentEntity['currency'] ?? 'INR';

        Log::info('Payment webhook', ['event' => $event, 'payment_id' => $razorpayPaymentId, 'status' => $status]);

        if (!$razorpayOrderId && !$razorpaySubscriptionId) {
            return;
        }

        $payment = null;
        if ($razorpayOrderId) {
            $payment = Payment::where('razorpay_order_id', $razorpayOrderId)->first();
        } elseif ($razorpayPaymentId) {
            $payment = Payment::where('razorpay_payment_id', $razorpayPaymentId)->first();
        }

        if (!$payment && $razorpaySubscriptionId) {
            $subscription = Subscription::where('razorpay_subscription_id', $razorpaySubscriptionId)->first();
            if ($subscription) {
                $payment = Payment::create([
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'amount' => $amount ?? 0,
                    'currency' => $currency ?? 'INR',
                    'status' => $status === 'captured' ? 'success' : 'pending',
                    'payment_method' => $method,
                    'description' => 'Razorpay subscription payment',
                    'paid_at' => $status === 'captured' ? now() : null,
                    'metadata' => [
                        'razorpay_subscription_id' => $razorpaySubscriptionId,
                        'event' => $event,
                    ],
                ]);
            }
        }

        if (!$payment) {
            return;
        }

        if (($event === 'payment.captured' || $status === 'captured') && $status === 'captured') {
            $payment->update([
                'payment_method' => $method ?? $payment->payment_method,
            ]);

            if ($razorpayPaymentId) {
                $payment->markAsSuccessful($razorpayPaymentId, $payment->razorpay_signature ?? '');
            } else {
                $payment->update(['status' => 'success', 'paid_at' => now()]);
                // Also mark user as paid when updating directly
                if ($payment->user && !$payment->user->has_paid_verification_fee) {
                    $payment->user->forceFill([
                        'has_paid_verification_fee' => true,
                        'verification_fee_paid_at' => now(),
                    ])->save();
                }
            }
        }

        if ($event === 'payment.failed' || $status === 'failed') {
            $payment->markAsFailed('Razorpay status failed');
        }
    }

    private function handleSubscriptionEvent(string $event, array $subscriptionEntity): void
    {
        $razorpaySubscriptionId = $subscriptionEntity['id'] ?? null;
        $status = $subscriptionEntity['status'] ?? null;

        Log::info('Subscription webhook', ['event' => $event, 'subscription_id' => $razorpaySubscriptionId, 'status' => $status]);

        if (!$razorpaySubscriptionId) {
            return;
        }

        $subscription = Subscription::where('razorpay_subscription_id', $razorpaySubscriptionId)->first();
        if (!$subscription) {
            Log::warning('Subscription not found for webhook', ['razorpay_subscription_id' => $razorpaySubscriptionId]);
            return;
        }

        switch ($event) {
            case 'subscription.authenticated':
                // User has authenticated the subscription (first Rs 2 paid)
                $subscription->update([
                    'status' => 'trial',
                ]);
                // Mark user as having paid AND set verification fee flag
                $subscription->user->forceFill([
                    'is_subscribed' => true,
                    'has_paid_verification_fee' => true,
                    'verification_fee_paid_at' => now(),
                ])->save();
                Log::info('Subscription authenticated - trial started, verification fee marked', ['subscription_id' => $subscription->id, 'user_id' => $subscription->user_id]);
                break;

            case 'subscription.activated':
                // Subscription is now active (after trial or immediate)
                $subscription->update([
                    'status' => 'active',
                    'is_trial' => false,
                ]);
                // Mark user as having paid AND set verification fee flag
                $subscription->user->forceFill([
                    'is_subscribed' => true,
                    'has_paid_verification_fee' => true,
                    'verification_fee_paid_at' => $subscription->user->verification_fee_paid_at ?? now(),
                ])->save();
                Log::info('Subscription activated', ['subscription_id' => $subscription->id]);
                break;

            case 'subscription.charged':
                // Recurring payment successful
                $currentEnd = $subscriptionEntity['current_end'] ?? null;
                $subscription->update([
                    'status' => 'active',
                    'ends_at' => $currentEnd ? date('Y-m-d H:i:s', $currentEnd) : $subscription->ends_at,
                    'next_billing_date' => $currentEnd ? date('Y-m-d H:i:s', $currentEnd) : null,
                ]);
                // Ensure verification fee flag is set
                if (!$subscription->user->has_paid_verification_fee) {
                    $subscription->user->forceFill([
                        'has_paid_verification_fee' => true,
                        'verification_fee_paid_at' => now(),
                    ])->save();
                }
                Log::info('Subscription charged', ['subscription_id' => $subscription->id]);
                break;

            case 'subscription.pending':
                // Payment pending
                Log::info('Subscription payment pending', ['subscription_id' => $subscription->id]);
                break;

            case 'subscription.halted':
                // Payment failed multiple times
                $subscription->update([
                    'status' => 'cancelled',
                    'auto_renew' => false,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Payment failed - subscription halted',
                ]);
                Log::info('Subscription halted', ['subscription_id' => $subscription->id]);
                break;

            case 'subscription.cancelled':
                // Subscription cancelled
                $subscription->update([
                    'status' => 'cancelled',
                    'auto_renew' => false,
                    'cancelled_at' => now(),
                ]);
                Log::info('Subscription cancelled', ['subscription_id' => $subscription->id]);
                break;

            case 'subscription.completed':
                // All billing cycles completed
                $subscription->update([
                    'status' => 'expired',
                    'auto_renew' => false,
                ]);
                $subscription->user->update(['is_subscribed' => false]);
                Log::info('Subscription completed', ['subscription_id' => $subscription->id]);
                break;

            case 'subscription.expired':
                // Subscription expired
                $subscription->update([
                    'status' => 'expired',
                ]);
                $subscription->user->update(['is_subscribed' => false]);
                Log::info('Subscription expired', ['subscription_id' => $subscription->id]);
                break;
        }
    }

    private function handleRefundEvent(string $event, array $refundEntity): void
    {
        $refundId = $refundEntity['id'] ?? null;
        $razorpayPaymentId = $refundEntity['payment_id'] ?? null;
        $amountPaise = (int) ($refundEntity['amount'] ?? 0);
        $status = $refundEntity['status'] ?? null;

        if (!$razorpayPaymentId) {
            return;
        }

        $payment = Payment::where('razorpay_payment_id', $razorpayPaymentId)->first();
        if (!$payment) {
            return;
        }

        $meta = $payment->metadata ?? [];
        $meta['refunds'] = $meta['refunds'] ?? [];
        $meta['refunds'][] = [
            'id' => $refundId,
            'amount' => round($amountPaise / 100, 2),
            'status' => $status,
            'event' => $event,
            'received_at' => now()->toIso8601String(),
        ];

        $payment->metadata = $meta;

        if (in_array($status, ['processed', 'successful', 'refunded'], true) || in_array($event, ['refund.processed'], true)) {
            $payment->status = 'refunded';
        }

        $payment->save();
    }
}
