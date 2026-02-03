<?php
/**
 * Fix: Skip Rs 2 payment for users who already paid verification fee
 * 
 * Changes:
 * 1. RazorpayService::createSubscription - Add $skipTrialFee parameter
 * 2. SubscriptionController::subscribe - Use skipTrialFee for reenable_autopay flow
 */

// ==============================================================
// 1. UPDATED RazorpayService.php
// ==============================================================
$razorpayServicePath = '/var/www/thaedal/api/app/Services/RazorpayService.php';
$razorpayServiceContent = <<<'PHP'
<?php

namespace App\Services;

use Razorpay\Api\Api;
use Illuminate\Support\Facades\Log;

class RazorpayService
{
    private ?Api $api = null;
    private ?string $keyId;
    private ?string $keySecret;

    public function __construct()
    {
        $this->keyId = config('services.razorpay.key_id');
        $this->keySecret = config('services.razorpay.key_secret');
    }

    private function api(): Api
    {
        if (!$this->keyId || !$this->keySecret) {
            throw new \RuntimeException('Razorpay is not configured (missing RAZORPAY_KEY_ID/RAZORPAY_KEY_SECRET).');
        }

        if ($this->api === null) {
            $this->api = new Api($this->keyId, $this->keySecret);
        }

        return $this->api;
    }

    public function getKeyId(): string
    {
        return $this->keyId ?? '';
    }

    /**
     * Create a Razorpay order
     */
    public function createOrder(float $amount, string $receipt, array $notes = []): array
    {
        try {
            $order = $this->api()->order->create([
                'amount' => (int) round($amount * 100), // paise
                'currency' => 'INR',
                'receipt' => $receipt,
                'notes' => $notes,
                'payment_capture' => 1,
            ]);

            Log::info("Razorpay order created: {$order->id}");

            return [
                'id' => $order->id,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'receipt' => $order->receipt,
                'status' => $order->status,
            ];
        } catch (\Throwable $e) {
            Log::error('Razorpay order creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify payment signature
     */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        try {
            if (!$this->keySecret) {
                Log::error('Razorpay signature verification failed: missing key secret');
                return false;
            }

            $expectedSignature = hash_hmac(
                'sha256',
                $orderId . '|' . $paymentId,
                $this->keySecret
            );

            return hash_equals($expectedSignature, $signature);
        } catch (\Throwable $e) {
            Log::error('Razorpay signature verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch payment details
     */
    public function fetchPayment(string $paymentId): array
    {
        try {
            $payment = $this->api()->payment->fetch($paymentId);

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
        } catch (\Throwable $e) {
            Log::error('Razorpay fetch payment failed: ' . $e->getMessage());
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
            if ($amount !== null) {
                $params['amount'] = (int) round($amount * 100);
            }

            $refund = $this->api()->refund->create([
                'payment_id' => $paymentId,
                ...$params,
            ]);

            Log::info("Razorpay refund created: {$refund->id}");

            return [
                'id' => $refund->id,
                'amount' => $refund->amount / 100,
                'status' => $refund->status,
            ];
        } catch (\Throwable $e) {
            Log::error('Razorpay refund failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * List payments in a time range (Razorpay API pagination).
     */
    public function listPayments(int $from, int $to, int $count = 100, int $skip = 0): array
    {
        $items = $this->api()->payment->all([
            'from' => $from,
            'to' => $to,
            'count' => $count,
            'skip' => $skip,
        ]);

        return $items['items'] ?? [];
    }

    /**
     * Fetch an order (used to get the receipt / our internal order id).
     */
    public function fetchOrder(string $razorpayOrderId): array
    {
        $order = $this->api()->order->fetch($razorpayOrderId);

        return [
            'id' => $order->id,
            'amount' => $order->amount / 100,
            'currency' => $order->currency,
            'receipt' => $order->receipt,
            'status' => $order->status,
            'created_at' => $order->created_at,
        ];
    }

    /**
     * List settlements in a time range (Razorpay API pagination).
     */
    public function listSettlements(int $from, int $to, int $count = 100, int $skip = 0): array
    {
        $items = $this->api()->settlement->all([
            'from' => $from,
            'to' => $to,
            'count' => $count,
            'skip' => $skip,
        ]);

        return $items['items'] ?? [];
    }

    /**
     * Create a Razorpay subscription
     * 
     * @param string $planId Razorpay plan ID
     * @param array $customerDetails Customer email/contact
     * @param array $notes Metadata
     * @param bool $skipTrialFee If true, skip the Rs 2 trial addon (for users who already paid)
     */
    public function createSubscription(string $planId, array $customerDetails = [], array $notes = [], bool $skipTrialFee = false): array
    {
        try {
            // Calculate trial end date (7 days from now)
            $trialEndTimestamp = strtotime('+7 days');
            
            $subscriptionData = [
                'plan_id' => $planId,
                'total_count' => 12, // 12 billing cycles (1 year for monthly)
                'customer_notify' => 0, // Don't spam with emails
                'start_at' => $trialEndTimestamp, // First Rs 99 charge after 7 days
                'notes' => $notes,
            ];

            // Add Rs 2 trial verification charge as addon (charged immediately)
            // SKIP this for users who already paid the verification fee
            if (!$skipTrialFee) {
                $subscriptionData['addons'] = [
                    [
                        'item' => [
                            'name' => 'Trial Verification',
                            'amount' => 200, // Rs 2 in paise
                            'currency' => 'INR',
                        ],
                    ],
                ];
            }

            $subscription = $this->api()->subscription->create($subscriptionData);

            Log::info("Razorpay subscription created: {$subscription->id}", [
                'skip_trial_fee' => $skipTrialFee,
            ]);

            return [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan_id' => $subscription->plan_id,
                'short_url' => $subscription->short_url ?? null,
                'current_start' => $subscription->current_start ?? null,
                'current_end' => $subscription->current_end ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Razorpay subscription creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify subscription payment signature
     */
    public function verifyPaymentSignature(string $subscriptionId, string $paymentId, string $signature): bool
    {
        try {
            if (!$this->keySecret) {
                return false;
            }

            $expectedSignature = hash_hmac(
                'sha256',
                $paymentId . '|' . $subscriptionId,
                $this->keySecret
            );

            return hash_equals($expectedSignature, $signature);
        } catch (\Throwable $e) {
            Log::error('Razorpay subscription signature verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch subscription details from Razorpay
     */
    public function fetchSubscription(string $subscriptionId): array
    {
        try {
            $subscription = $this->api()->subscription->fetch($subscriptionId);
            
            return [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan_id' => $subscription->plan_id,
                'current_start' => $subscription->current_start ?? null,
                'current_end' => $subscription->current_end ?? null,
                'charge_at' => $subscription->charge_at ?? null,
                'paid_count' => $subscription->paid_count ?? 0,
                'auth_attempts' => $subscription->auth_attempts ?? 0,
            ];
        } catch (\Throwable $e) {
            Log::error('Razorpay fetch subscription failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
PHP;

// ==============================================================
// 2. UPDATED SubscriptionController.php - subscribe method
// ==============================================================
$subscriptionControllerPath = '/var/www/thaedal/api/app/Http/Controllers/Api/SubscriptionController.php';
$subscriptionControllerContent = <<<'PHP'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    protected RazorpayService $razorpay;

    public function __construct(RazorpayService $razorpay)
    {
        $this->razorpay = $razorpay;
    }

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()->orderBy('price')->get();
        return $this->success($plans, 'Subscription plans retrieved');
    }

    public function mySubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $subscription = $user->subscriptions()->valid()->with('plan')->first();
        
        if ($subscription) {
            return $this->success([
                'has_subscription' => true,
                'subscription' => new SubscriptionResource($subscription),
            ], 'Subscription retrieved');
        }
        
        if ($user->is_subscribed) {
            return $this->success([
                'has_subscription' => true,
                'subscription' => [
                    'id' => 'admin-granted',
                    'status' => 'active',
                    'is_trial' => false,
                    'starts_at' => $user->created_at,
                    'ends_at' => null,
                    'auto_renew' => false,
                    'plan' => [
                        'name' => 'Premium Access',
                        'price' => 0,
                        'duration_type' => 'unlimited',
                    ],
                ],
            ], 'Admin-granted subscription');
        }

        return $this->success([
            'has_subscription' => false,
            'subscription' => [
                'id' => null,
                'user_id' => $user->id,
                'plan_id' => null,
                'status' => 'none',
                'is_trial' => false,
                'trial_ends_at' => null,
                'starts_at' => null,
                'ends_at' => null,
                'auto_renew' => false,
                'payment_method_id' => null,
                'next_billing_date' => null,
                'cancelled_at' => null,
                'days_remaining' => 0,
                'is_active' => false,
                'created_at' => null,
                'updated_at' => null,
                'plan' => [
                    'id' => null,
                    'name' => 'No Plan',
                    'name_tamil' => null,
                    'description' => null,
                    'price' => 0,
                    'currency' => 'INR',
                    'formatted_price' => '₹0',
                    'duration_days' => 0,
                    'duration_type' => 'none',
                    'duration_label' => 'None',
                    'trial_days' => 0,
                    'features' => [],
                    'is_popular' => false,
                    'discount_percentage' => 0,
                    'original_price' => null,
                    'formatted_original_price' => null,
                    'has_discount' => false,
                ],
            ],
        ], 'No active subscription');
    }

    /**
     * Create subscription using Razorpay Subscriptions API
     */
    public function subscribe(Request $request): JsonResponse
    {
        Log::info('Subscribe/create-subscription request', ['all_input' => $request->all()]);

        $request->validate([
            'plan_id' => 'required|string',
            'flow' => 'nullable|string',
        ]);

        $user = $request->user();
        $isReenableFlow = ($request->flow === 'reenable_autopay');
        $alreadyPaidVerification = (bool) $user->has_paid_verification_fee;

        // For new users trying to pay Rs 2 again - reject if already paid (unless reenable flow)
        if ($alreadyPaidVerification && !$isReenableFlow) {
            // Auto-switch to reenable flow instead of rejecting
            $isReenableFlow = true;
            Log::info('Auto-switching to reenable_autopay flow for already-paid user', [
                'user_id' => $user->id,
            ]);
        }
        
        // Find plan by ID or duration_type (e.g., "monthly")
        $plan = SubscriptionPlan::active()
            ->where(function($q) use ($request) {
                $q->where('id', $request->plan_id)
                  ->orWhere('duration_type', $request->plan_id);
            })
            ->first();

        if (!$plan) {
            return $this->error('Plan not found or inactive', 404);
        }

        if (!$plan->razorpay_plan_id) {
            return $this->error('Plan not configured in Razorpay', 500);
        }

        // Check for existing subscription with valid Razorpay ID
        $existingSubscription = $user->subscriptions()->latest('created_at')->first();
        if ($existingSubscription && $existingSubscription->razorpay_subscription_id) {
            // For reenable flow with existing subscription, just re-enable autopay flag
            if ($isReenableFlow) {
                $existingSubscription->update(['auto_renew' => true]);
                return $this->success([
                    'autopay_reenabled' => true,
                    'subscription_id' => $existingSubscription->id,
                    'razorpay_subscription_id' => $existingSubscription->razorpay_subscription_id,
                    'key_id' => $this->razorpay->getKeyId(),
                ], 'Autopay re-enabled');
            }
            
            // Return existing subscription for payment
            if (in_array($existingSubscription->status, ['created', 'trial', 'active', 'authenticated'], true)) {
                return $this->created([
                    'subscription_id' => $existingSubscription->id,
                    'razorpay_subscription_id' => $existingSubscription->razorpay_subscription_id,
                    'key_id' => $this->razorpay->getKeyId(),
                    'amount' => $alreadyPaidVerification ? 0.0 : 2.0,
                    'currency' => 'INR',
                    'is_trial' => (bool) $existingSubscription->is_trial,
                    'trial_days' => (int) ($existingSubscription->trial_days ?? 7),
                    'plan_name' => $plan->name,
                    'plan_price' => (float) $plan->price,
                ], 'Subscription already created');
            }
        }

        if ($user->is_subscribed) {
            return $this->error('You already have an active subscription', 422);
        }

        try {
            // Skip Rs 2 addon if user already paid verification fee
            $skipTrialFee = $alreadyPaidVerification;
            
            Log::info('Creating Razorpay subscription', [
                'plan_id' => $plan->id,
                'razorpay_plan_id' => $plan->razorpay_plan_id,
                'skip_trial_fee' => $skipTrialFee,
                'flow' => $request->flow,
            ]);

            // Create Razorpay subscription (skip Rs 2 if already paid)
            $razorpaySubscription = $this->razorpay->createSubscription(
                $plan->razorpay_plan_id,
                ['email' => $user->email, 'contact' => $user->phone_number],
                ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id],
                $skipTrialFee
            );

            Log::info('Razorpay subscription created', [
                'subscription_id' => $razorpaySubscription['id'],
                'status' => $razorpaySubscription['status'],
                'skip_trial_fee' => $skipTrialFee,
            ]);

            // Create local subscription record
            $subscription = Subscription::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'razorpay_subscription_id' => $razorpaySubscription['id'],
                'status' => 'created',
                'is_trial' => !$skipTrialFee, // Not a trial if they already paid before
                'trial_days' => 7,
                'trial_ends_at' => now()->addDays(7),
                'starts_at' => now(),
                'ends_at' => now()->addDays(7),
                'auto_renew' => true,
                'next_billing_date' => now()->addDays(7),
            ]);

            return $this->created([
                'subscription_id' => $subscription->id,
                'razorpay_subscription_id' => $razorpaySubscription['id'],
                'key_id' => $this->razorpay->getKeyId(),
                'amount' => $skipTrialFee ? 0.0 : 2.0,
                'currency' => 'INR',
                'is_trial' => !$skipTrialFee,
                'trial_days' => 7,
                'plan_name' => $plan->name,
                'plan_price' => (float) $plan->price,
                'skip_payment' => $skipTrialFee, // Tell app to skip Rs 2 payment
            ], 'Subscription created');

        } catch (\Exception $e) {
            Log::error('Subscription creation failed: ' . $e->getMessage(), ['exception' => $e]);
            return $this->error('Failed to create subscription: ' . $e->getMessage(), 500);
        }
    }

    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()->valid()->first();

        if (!$subscription) {
            return $this->error('No active subscription to cancel', 404);
        }

        $subscription->update([
            'auto_renew' => false,
            'status' => 'cancelled',
        ]);

        return $this->success([
            'subscription' => new SubscriptionResource($subscription->fresh()),
        ], 'Subscription cancelled');
    }

    /**
     * Verify payment after Razorpay checkout
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_subscription_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $user = $request->user();

        try {
            // Verify signature
            $isValid = $this->razorpay->verifyPaymentSignature(
                $request->razorpay_subscription_id,
                $request->razorpay_payment_id,
                $request->razorpay_signature
            );

            if (!$isValid) {
                Log::warning('Payment signature verification failed', [
                    'subscription_id' => $request->razorpay_subscription_id,
                    'payment_id' => $request->razorpay_payment_id,
                ]);
                return $this->error('Payment verification failed', 400);
            }

            // Find and activate subscription
            $subscription = Subscription::where('razorpay_subscription_id', $request->razorpay_subscription_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$subscription) {
                return $this->error('Subscription not found', 404);
            }

            // Activate subscription
            $subscription->update([
                'status' => 'trial',
                'razorpay_payment_id' => $request->razorpay_payment_id,
            ]);

            // Mark user as subscribed AND set verification fee flag
            $user->forceFill([
                'is_subscribed' => true,
                'has_paid_verification_fee' => true,
                'verification_fee_paid_at' => $user->verification_fee_paid_at ?? now(),
            ])->save();

            Log::info('Trial subscription activated', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
            ]);

            return $this->success([
                'subscription' => new SubscriptionResource($subscription->fresh()->load('plan')),
                'message' => 'Trial activated! Enjoy 7 days of premium access.',
            ], 'Payment verified');

        } catch (\Exception $e) {
            Log::error('Payment verification error: ' . $e->getMessage());
            return $this->error('Payment verification failed', 500);
        }
    }

    /**
     * Enable autopay for subscription
     */
    public function enableAutopay(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()->valid()->first();

        if (!$subscription) {
            return $this->error('No active subscription found', 404);
        }

        $subscription->update(['auto_renew' => true]);

        return $this->success([
            'subscription' => new SubscriptionResource($subscription->fresh()->load('plan')),
            'auto_renew' => true,
        ], 'Autopay enabled');
    }

    /**
     * Disable autopay for subscription
     */
    public function disableAutopay(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()->valid()->first();

        if (!$subscription) {
            return $this->error('No active subscription found', 404);
        }

        $subscription->update(['auto_renew' => false]);

        return $this->success([
            'subscription' => new SubscriptionResource($subscription->fresh()->load('plan')),
            'auto_renew' => false,
        ], 'Autopay disabled');
    }

    /**
     * Retry payment for existing "created" subscription
     * Returns the existing razorpay_subscription_id so user can retry payment
     */
    public function retryPayment(Request $request): JsonResponse
    {
        $user = $request->user();

        // Find existing subscription in "created" status (payment was not completed)
        $subscription = $user->subscriptions()
            ->where('status', 'created')
            ->whereNotNull('razorpay_subscription_id')
            ->latest('created_at')
            ->first();

        if (!$subscription) {
            return $this->error('No pending subscription found to retry', 404);
        }

        $plan = $subscription->plan;

        Log::info('Retry payment requested', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'razorpay_subscription_id' => $subscription->razorpay_subscription_id,
        ]);

        return $this->success([
            'subscription_id' => $subscription->id,
            'razorpay_subscription_id' => $subscription->razorpay_subscription_id,
            'key_id' => $this->razorpay->getKeyId(),
            'amount' => 2.0,
            'currency' => 'INR',
            'is_trial' => (bool) $subscription->is_trial,
            'trial_days' => (int) ($subscription->trial_days ?? 7),
            'plan_name' => $plan->name ?? 'Monthly Plan',
            'plan_price' => (float) ($plan->price ?? 299),
            'is_retry' => true,
        ], 'Retry payment for existing subscription');
    }

    /**
     * Check if user has a pending subscription that can be retried
     */
    public function checkPendingSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        $pending = $user->subscriptions()
            ->where('status', 'created')
            ->whereNotNull('razorpay_subscription_id')
            ->latest('created_at')
            ->first();

        return $this->success([
            'has_pending' => (bool) $pending,
            'can_retry' => (bool) $pending,
            'subscription_id' => $pending?->id,
            'razorpay_subscription_id' => $pending?->razorpay_subscription_id,
        ], 'Pending subscription check');
    }

    /**
     * Verify subscription payment (alias for verifyPayment)
     */
    public function verifySubscription(Request $request): JsonResponse
    {
        return $this->verifyPayment($request);
    }
}
PHP;

// Deploy the files
file_put_contents($razorpayServicePath, $razorpayServiceContent);
echo "Updated: $razorpayServicePath\n";

file_put_contents($subscriptionControllerPath, $subscriptionControllerContent);
echo "Updated: $subscriptionControllerPath\n";

echo "\nDone! Clear cache with:\n";
echo "php artisan config:clear && php artisan cache:clear && php artisan route:clear\n";
