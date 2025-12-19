<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    /**
     * Get available subscription plans
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->ordered()
            ->get();

        return $this->success(SubscriptionPlanResource::collection($plans));
    }

    /**
     * Get current user's subscription
     */
    public function mySubscription(Request $request): JsonResponse
    {
        $subscription = $request->user()
            ->subscriptions()
            ->with('plan')
            ->valid()
            ->latest()
            ->first();

        if (!$subscription) {
            return $this->success(null, 'No active subscription');
        }

        return $this->success(new SubscriptionResource($subscription));
    }

    /**
     * Subscribe to a plan (initiates subscription, actual payment handled separately)
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'payment_method_id' => 'nullable|uuid|exists:payment_methods,id',
            'auto_renew' => 'boolean',
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::active()->find($request->plan_id);

        if (!$plan) {
            return $this->error('Plan not found or inactive', 404);
        }

        // Check if user already has active subscription
        $existingSubscription = $user->subscriptions()->valid()->first();
        if ($existingSubscription) {
            return $this->error('You already have an active subscription', 422);
        }

        // Create subscription (pending payment verification)
        $subscription = Subscription::create([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => $plan->trial_days > 0 ? 'trial' : 'pending',
            'is_trial' => $plan->trial_days > 0,
            'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
            'starts_at' => now(),
            'ends_at' => $plan->trial_days > 0 
                ? now()->addDays($plan->trial_days) 
                : now()->addDays($plan->duration_days),
            'auto_renew' => $request->input('auto_renew', true),
            'payment_method_id' => $request->payment_method_id,
            'next_billing_date' => $plan->trial_days > 0 
                ? now()->addDays($plan->trial_days)
                : now()->addDays($plan->duration_days),
        ]);

        // Update user subscription status
        $user->update(['is_subscribed' => true]);

        $subscription->load('plan');

        return $this->created(new SubscriptionResource($subscription), 'Subscription created');
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $subscription = $user->subscriptions()->valid()->first();

        if (!$subscription) {
            return $this->error('No active subscription found', 404);
        }

        $subscription->cancel($request->reason);

        // Note: User remains subscribed until ends_at
        // Don't update is_subscribed here

        return $this->success(
            new SubscriptionResource($subscription->fresh('plan')),
            'Subscription cancelled. You can still access content until ' . $subscription->ends_at->format('M d, Y')
        );
    }

    /**
     * Enable autopay
     */
    public function enableAutopay(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method_id' => 'required|uuid|exists:payment_methods,id',
        ]);

        $user = $request->user();
        $subscription = $user->subscriptions()->valid()->first();

        if (!$subscription) {
            return $this->error('No active subscription found', 404);
        }

        // Verify payment method belongs to user
        $paymentMethod = $user->paymentMethods()->find($request->payment_method_id);
        if (!$paymentMethod) {
            return $this->error('Payment method not found', 404);
        }

        $subscription->update([
            'auto_renew' => true,
            'payment_method_id' => $request->payment_method_id,
        ]);

        return $this->success(
            new SubscriptionResource($subscription->fresh('plan')),
            'Autopay enabled'
        );
    }

    /**
     * Disable autopay
     */
    public function disableAutopay(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()->valid()->first();

        if (!$subscription) {
            return $this->error('No active subscription found', 404);
        }

        $subscription->update([
            'auto_renew' => false,
        ]);

        return $this->success(
            new SubscriptionResource($subscription->fresh('plan')),
            'Autopay disabled'
        );
    }
}

