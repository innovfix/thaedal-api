<?php
/**
 * FIX: Cat1 users should NOT have access to videos
 * Bug: accessActive was true for users with subscription records even if they never paid
 */

$controllerPath = '/var/www/thaedal/api/app/Http/Controllers/Api/PaywallController.php';

$newContent = <<<'PHP'
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $subscriptionStatus = null;
        $nextBillingDate = null;

        if ($subscription) {
            $subscriptionStatus = $subscription->status;
            $nextBillingDate = optional($subscription->next_billing_date)->toDateTimeString();
        }

        // CRITICAL: Check if user actually paid the verification fee
        $hasPaid = (bool)($user->has_paid_verification_fee ?? false);

        // ACCESS LOGIC FIX:
        // Cat1 (never paid) = NO access, period
        // Cat2 (paid + autopay on) = Full access
        // Cat3 (paid + autopay off) = Access for 7 days after payment
        
        $accessEndsAt = null;
        $accessActive = false;
        
        // Only calculate access if user HAS PAID
        if ($hasPaid) {
            // Check subscription ends_at first
            if ($subscription && $subscription->ends_at && 
                in_array($subscription->status, ['active', 'trial', 'authenticated'])) {
                $accessEndsAt = $subscription->ends_at;
            }
            // Fallback: 7 days from verification fee payment
            elseif ($user->verification_fee_paid_at) {
                $accessEndsAt = \Carbon\Carbon::parse($user->verification_fee_paid_at)->addDays(7);
            }
            
            $accessActive = $accessEndsAt ? $accessEndsAt->gt(now()) : false;
        }
        // Cat1 users (never paid): accessActive is always false

        // Determine if autopay is truly on
        $hasRazorpaySub = $subscription && !empty($subscription->razorpay_subscription_id);
        $isValidStatus = $subscription && in_array($subscription->status, ['active', 'trial', 'authenticated'], true);
        $autoRenewOn = $subscription && (bool)($subscription->auto_renew ?? false);
        
        // Real autopay = has Razorpay subscription + auto_renew on + valid status + user paid
        $isAutopayOn = $hasPaid && $hasRazorpaySub && $autoRenewOn && $isValidStatus;
        
        // Admin can force premium
        $forcePremium = (bool)($user->is_subscribed ?? false);

        // Determine category
        if ($isAutopayOn || $forcePremium) {
            $category = 'Cat2_AutopayOn';
            if (!$subscriptionStatus && $forcePremium) {
                $subscriptionStatus = 'active';
            }
        } elseif (!$hasPaid) {
            // Cat1: Never paid - NO ACCESS
            $category = 'Cat1_New';
            $accessActive = false; // Ensure no access
        } else {
            // Cat3: Paid but autopay is off
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

        // Effective category: Cat3 with active access is treated like Cat2 for video playback
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
}
PHP;

file_put_contents($controllerPath, $newContent);
echo "✅ Fixed PaywallController - Cat1 users now blocked from videos!\n";
echo "\nNew Logic:\n";
echo "- Cat1 (never paid): accessActive = FALSE always\n";
echo "- Cat2 (paid + real Razorpay autopay): accessActive = TRUE\n";
echo "- Cat3 (paid, no autopay): accessActive = TRUE for 7 days after payment\n";
