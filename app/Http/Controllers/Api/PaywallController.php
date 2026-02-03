<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaywallController extends Controller
{
    public function state(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = PaymentSetting::current();

        $subscription = $user->subscriptions()
            ->with('plan')
            ->latest('created_at')
            ->first();

        $subscriptionStatus = $subscription ? $subscription->status : null;
        $nextBillingDate = optional($subscription->next_billing_date ?? null)->toDateTimeString();

        // CRITICAL: Check if user actually paid the verification fee
        $hasPaid = (bool)($user->has_paid_verification_fee ?? false);

        // Check for real Razorpay autopay
        $hasRazorpaySub = $subscription && !empty($subscription->razorpay_subscription_id);
        $isValidStatus = $subscription && in_array($subscription->status, ['active', 'trial', 'authenticated'], true);
        $autoRenewOn = $subscription && (bool)($subscription->auto_renew ?? false);
        
        // Real autopay = paid + has Razorpay subscription + auto_renew on + valid status
        $isRealAutopayOn = $hasPaid && $hasRazorpaySub && $autoRenewOn && $isValidStatus;
        
        // Admin can force premium
        $forcePremium = (bool)($user->is_subscribed ?? false);

        // ACCESS LOGIC:
        // - Cat1 (never paid): NO access
        // - Cat2 (paid + real autopay OR admin forced): ALWAYS access
        // - Cat3 (paid, no autopay): 7 days from payment date
        
        $accessEndsAt = null;
        $accessActive = false;
        
        if ($isRealAutopayOn || $forcePremium) {
            // Cat2: Full access always (autopay will auto-renew)
            $accessActive = true;
            $accessEndsAt = $subscription->ends_at ?? null;
        } elseif ($hasPaid) {
            // Cat3: Access for 7 days from payment
            if ($user->verification_fee_paid_at) {
                $accessEndsAt = \Carbon\Carbon::parse($user->verification_fee_paid_at)->addDays(7);
                $accessActive = $accessEndsAt->gt(now());
            }
        }
        // Cat1: accessActive stays false

        // Determine category
        if ($isRealAutopayOn || $forcePremium) {
            $category = 'Cat2_AutopayOn';
            if (!$subscriptionStatus && $forcePremium) {
                $subscriptionStatus = 'active';
            }
        } elseif (!$hasPaid) {
            $category = 'Cat1_New';
        } else {
            $category = 'Cat3_AutopayOffAfter2';
        }

        // Should show autopay prompt for Cat3 users
        $shouldShowAutopayPrompt = false;
        if ($category === 'Cat3_AutopayOffAfter2') {
            if (!$accessActive) {
                $shouldShowAutopayPrompt = true;
            } else {
                $last = $user->last_autopay_prompt_at;
                $shouldShowAutopayPrompt = !$last || $last->lt(now()->subDays(30));
            }
        }

        // Effective category: Cat3 with active access is treated like Cat2 for UI
        $effectiveCategory = $category;
        if ($category === 'Cat3_AutopayOffAfter2' && $accessActive) {
            $effectiveCategory = 'Cat2_AutopayOn';
        }

        return $this->success([
            'category' => $effectiveCategory,
            'real_category' => $category,
            'has_paid_verification_fee' => $hasPaid,
            'verification_fee_paid_at' => optional($user->verification_fee_paid_at)->toDateTimeString(),
            'subscription_status' => $subscriptionStatus,
            'next_billing_date' => $nextBillingDate,
            'should_show_autopay_prompt' => $shouldShowAutopayPrompt,
            'access_active' => (bool) $accessActive,
            'access_ends_at' => $accessEndsAt ? $accessEndsAt->toIso8601String() : null,
            'pricing' => [
                'verification_fee_amount_paise' => (int)$settings->verification_fee_amount_paise,
                'autopay_amount_paise' => (int)$settings->autopay_amount_paise,
                'pricing_version' => (int)$settings->pricing_version,
                'pricing_updated_at' => optional($settings->pricing_updated_at)->toDateTimeString(),
            ],
            'paywall_video' => [
                'type' => $settings->paywall_video_type,
                'url' => $settings->paywallVideoUrl(),
            ],
        ]);
    }

    public function autopayPromptShown(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['last_autopay_prompt_at' => now()])->save();
        return $this->success(['last_autopay_prompt_at' => optional($user->last_autopay_prompt_at)->toDateTimeString()]);
    }

    /**
     * Track paywall demo video view count (once per user per day).
     */
    public function paywallVideoViewed(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = PaymentSetting::current();

        $key = 'paywall_video_viewed:' . $user->id . ':' . now()->toDateString();
        if (!Cache::has($key)) {
            Cache::put($key, true, now()->addDays(2));
            $settings->increment('paywall_video_view_count');
        }

        return $this->success([
            'view_count' => (int) ($settings->paywall_video_view_count ?? 0),
        ], 'Paywall video view tracked');
    }
}