<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SubscriptionPlan;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        protected RazorpayService $razorpay
    ) {}

    /**
     * Get user's payment methods
     */
    public function methods(Request $request): JsonResponse
    {
        $methods = $request->user()
            ->paymentMethods()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return $this->success(PaymentMethodResource::collection($methods));
    }

    /**
     * Add a new payment method
     */
    public function addMethod(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:card,upi',
            // Card fields
            'card_number' => 'required_if:type,card|string',
            'exp_month' => 'required_if:type,card|integer|between:1,12',
            'exp_year' => 'required_if:type,card|integer|min:' . date('Y'),
            'cvv' => 'required_if:type,card|string|size:3',
            'holder_name' => 'required_if:type,card|string|max:100',
            // UPI fields
            'vpa' => 'required_if:type,upi|string|regex:/^[\w.-]+@[\w]+$/',
            // Common
            'set_default' => 'boolean',
        ]);

        $user = $request->user();

        if ($request->type === 'card') {
            // Detect card brand
            $cardNumber = preg_replace('/\s+/', '', $request->card_number);
            $brand = $this->detectCardBrand($cardNumber);
            $lastFour = substr($cardNumber, -4);

            $method = PaymentMethod::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'type' => 'card',
                'is_default' => $request->input('set_default', false),
                'details' => [
                    'holder_name' => $request->holder_name,
                    'exp_month' => $request->exp_month,
                    'exp_year' => $request->exp_year,
                    // Never store full card number or CVV
                ],
                'last_four' => $lastFour,
                'brand' => $brand,
            ]);
        } else {
            $method = PaymentMethod::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'type' => 'upi',
                'is_default' => $request->input('set_default', false),
                'details' => [
                    'vpa' => $request->vpa,
                ],
            ]);
        }

        if ($request->input('set_default', false)) {
            $method->setAsDefault();
        }

        return $this->created(new PaymentMethodResource($method), 'Payment method added');
    }

    /**
     * Remove a payment method
     */
    public function removeMethod(Request $request, string $id): JsonResponse
    {
        $method = $request->user()->paymentMethods()->find($id);

        if (!$method) {
            return $this->notFound('Payment method not found');
        }

        // Check if method is used for autopay
        $activeSubscription = $request->user()
            ->subscriptions()
            ->valid()
            ->where('payment_method_id', $id)
            ->where('auto_renew', true)
            ->first();

        if ($activeSubscription) {
            return $this->error('Cannot remove payment method used for autopay', 422);
        }

        $method->delete();

        return $this->success(null, 'Payment method removed');
    }

    /**
     * Get payment history
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);

        $payments = $request->user()
            ->payments()
            ->recent()
            ->paginate($perPage);

        return $this->paginated(
            $payments->setCollection(
                PaymentResource::collection($payments->getCollection())
            )
        );
    }

    /**
     * Initiate a payment
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'payment_method_id' => 'nullable|uuid|exists:payment_methods,id',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::active()->find($request->plan_id);

        if (!$plan) {
            return $this->error('Plan not found', 404);
        }

        $amount = $plan->price;

        // TODO: Apply coupon discount if provided

        // Create Razorpay order
        try {
            $orderId = Payment::generateOrderId();
            $razorpayOrder = $this->razorpay->createOrder($amount, $orderId);

            // Create payment record
            $payment = Payment::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'order_id' => $orderId,
                'razorpay_order_id' => $razorpayOrder['id'],
                'amount' => $amount,
                'currency' => 'INR',
                'status' => 'pending',
                'description' => "Subscription: {$plan->name}",
                'metadata' => [
                    'plan_id' => $plan->id,
                    'coupon_code' => $request->coupon_code,
                ],
            ]);

            return $this->success([
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => 'INR',
                'razorpay_order_id' => $razorpayOrder['id'],
                'razorpay_key' => config('services.razorpay.key_id'),
                'notes' => [
                    'plan_name' => $plan->name,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to initiate payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify payment
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|string|exists:payments,order_id',
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $payment = Payment::where('order_id', $request->order_id)->first();

        if (!$payment || $payment->user_id !== $request->user()->id) {
            return $this->error('Payment not found', 404);
        }

        if ($payment->status === 'success') {
            return $this->error('Payment already verified', 422);
        }

        // Verify signature
        $isValid = $this->razorpay->verifySignature(
            $request->razorpay_order_id,
            $request->razorpay_payment_id,
            $request->razorpay_signature
        );

        if (!$isValid) {
            $payment->markAsFailed('Invalid signature');
            return $this->error('Payment verification failed', 422);
        }

        // Mark payment as successful
        $payment->markAsSuccessful(
            $request->razorpay_payment_id,
            $request->razorpay_signature
        );

        // Activate subscription
        $planId = $payment->metadata['plan_id'] ?? null;
        if ($planId) {
            $plan = SubscriptionPlan::find($planId);
            if ($plan) {
                $user = $request->user();
                
                // Create or update subscription
                $subscription = $user->subscriptions()->updateOrCreate(
                    ['status' => 'pending'],
                    [
                        'id' => Str::uuid(),
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'is_trial' => false,
                        'starts_at' => now(),
                        'ends_at' => now()->addDays($plan->duration_days),
                        'auto_renew' => true,
                        'next_billing_date' => now()->addDays($plan->duration_days),
                    ]
                );

                $payment->update(['subscription_id' => $subscription->id]);
                $user->update(['is_subscribed' => true]);
            }
        }

        return $this->success([
            'payment' => new PaymentResource($payment->fresh()),
            'subscription' => $payment->subscription 
                ? new \App\Http\Resources\SubscriptionResource($payment->subscription->load('plan'))
                : null,
        ], 'Payment successful');
    }

    /**
     * Detect card brand from number
     */
    private function detectCardBrand(string $number): string
    {
        $patterns = [
            'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
            'mastercard' => '/^5[1-5][0-9]{14}$/',
            'amex' => '/^3[47][0-9]{13}$/',
            'rupay' => '/^(508|60|65|81|82)[0-9]{14,17}$/',
        ];

        foreach ($patterns as $brand => $pattern) {
            if (preg_match($pattern, $number)) {
                return $brand;
            }
        }

        return 'unknown';
    }
}

