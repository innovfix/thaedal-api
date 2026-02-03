<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== TESTING CAT1/CAT2/CAT3 ACCESS LOGIC ===\n\n";

// Find a Cat1 user (never paid)
$cat1User = User::where('has_paid_verification_fee', false)
    ->orWhereNull('has_paid_verification_fee')
    ->first();

if ($cat1User) {
    echo "CAT1 USER: {$cat1User->name} ({$cat1User->phone_number})\n";
    echo "  has_paid_verification_fee: " . ($cat1User->has_paid_verification_fee ? 'YES' : 'NO') . "\n";
    
    // Simulate paywall state calculation
    $subscription = $cat1User->subscriptions()->latest('created_at')->first();
    $hasPaid = (bool)($cat1User->has_paid_verification_fee ?? false);
    
    $accessActive = false;
    if ($hasPaid) {
        if ($subscription && $subscription->ends_at) {
            $accessActive = $subscription->ends_at->gt(now());
        } elseif ($cat1User->verification_fee_paid_at) {
            $accessActive = \Carbon\Carbon::parse($cat1User->verification_fee_paid_at)->addDays(7)->gt(now());
        }
    }
    
    echo "  accessActive: " . ($accessActive ? 'TRUE ❌ BUG!' : 'FALSE ✅') . "\n";
    echo "  Expected: FALSE (Cat1 should NOT have access)\n";
}

echo "\n";

// Find a Cat3 user (paid but no real autopay)
$cat3User = User::where('has_paid_verification_fee', true)
    ->whereHas('subscriptions', function($q) {
        $q->whereNull('razorpay_subscription_id')
          ->orWhere('status', '!=', 'active');
    })
    ->first();

if ($cat3User) {
    echo "CAT3 USER: {$cat3User->name} ({$cat3User->phone_number})\n";
    echo "  has_paid_verification_fee: " . ($cat3User->has_paid_verification_fee ? 'YES' : 'NO') . "\n";
    echo "  verification_fee_paid_at: " . ($cat3User->verification_fee_paid_at ?? 'NULL') . "\n";
    
    $hasPaid = (bool)($cat3User->has_paid_verification_fee ?? false);
    $subscription = $cat3User->subscriptions()->latest('created_at')->first();
    
    $accessActive = false;
    $accessEndsAt = null;
    if ($hasPaid) {
        if ($subscription && $subscription->ends_at && in_array($subscription->status, ['active', 'trial', 'authenticated'])) {
            $accessEndsAt = $subscription->ends_at;
        } elseif ($cat3User->verification_fee_paid_at) {
            $accessEndsAt = \Carbon\Carbon::parse($cat3User->verification_fee_paid_at)->addDays(7);
        }
        $accessActive = $accessEndsAt ? $accessEndsAt->gt(now()) : false;
    }
    
    echo "  accessEndsAt: " . ($accessEndsAt ? $accessEndsAt->format('Y-m-d H:i') : 'NULL') . "\n";
    echo "  accessActive: " . ($accessActive ? 'TRUE' : 'FALSE') . "\n";
    echo "  Expected: TRUE if within 7 days of payment\n";
}

echo "\n";

// Find a Cat2 user (paid + real Razorpay autopay)
$cat2User = User::where('has_paid_verification_fee', true)
    ->whereHas('subscriptions', function($q) {
        $q->whereNotNull('razorpay_subscription_id')
          ->whereIn('status', ['active', 'trial'])
          ->where('auto_renew', true);
    })
    ->first();

if ($cat2User) {
    echo "CAT2 USER: {$cat2User->name} ({$cat2User->phone_number})\n";
    echo "  has_paid_verification_fee: YES\n";
    $subscription = $cat2User->subscriptions()->whereNotNull('razorpay_subscription_id')->first();
    echo "  razorpay_subscription_id: " . ($subscription->razorpay_subscription_id ?? 'NULL') . "\n";
    echo "  auto_renew: " . ($subscription->auto_renew ? 'YES' : 'NO') . "\n";
    echo "  Expected: Full access (Cat2)\n";
} else {
    echo "CAT2 USER: None found with real Razorpay autopay\n";
}
