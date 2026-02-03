<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\PaymentSetting;

echo "=== TESTING PAYWALL API RESPONSE FOR EACH CATEGORY ===\n\n";

function simulatePaywallState($user) {
    $settings = PaymentSetting::current();
    
    $subscription = $user->subscriptions()
        ->with('plan')
        ->latest('created_at')
        ->first();

    $subscriptionStatus = $subscription ? $subscription->status : null;
    $hasPaid = (bool)($user->has_paid_verification_fee ?? false);

    // Check for real Razorpay autopay
    $hasRazorpaySub = $subscription && !empty($subscription->razorpay_subscription_id);
    $isValidStatus = $subscription && in_array($subscription->status, ['active', 'trial', 'authenticated'], true);
    $autoRenewOn = $subscription && (bool)($subscription->auto_renew ?? false);
    
    // Real autopay = paid + has Razorpay subscription + auto_renew on + valid status
    $isRealAutopayOn = $hasPaid && $hasRazorpaySub && $autoRenewOn && $isValidStatus;
    
    // Admin can force premium
    $forcePremium = (bool)($user->is_subscribed ?? false);

    // ACCESS LOGIC (matching controller)
    $accessEndsAt = null;
    $accessActive = false;
    
    if ($isRealAutopayOn || $forcePremium) {
        // Cat2: Full access always
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

    // Category
    if ($isRealAutopayOn || $forcePremium) {
        $category = 'Cat2_AutopayOn';
    } elseif (!$hasPaid) {
        $category = 'Cat1_New';
    } else {
        $category = 'Cat3_AutopayOffAfter2';
    }

    $effectiveCategory = $category;
    if ($category === 'Cat3_AutopayOffAfter2' && $accessActive) {
        $effectiveCategory = 'Cat2_AutopayOn';
    }

    return [
        'user' => $user->name . ' (' . $user->phone_number . ')',
        'has_paid' => $hasPaid,
        'subscription_status' => $subscriptionStatus,
        'razorpay_sub_id' => $subscription->razorpay_subscription_id ?? null,
        'auto_renew' => $subscription->auto_renew ?? null,
        'is_real_autopay' => $isRealAutopayOn,
        'force_premium' => $forcePremium,
        'category' => $effectiveCategory,
        'real_category' => $category,
        'access_active' => $accessActive,
        'access_ends_at' => $accessEndsAt ? (is_string($accessEndsAt) ? $accessEndsAt : $accessEndsAt->format('Y-m-d H:i')) : null,
        'CAN_WATCH_VIDEO' => $accessActive ? '✅ YES' : '❌ NO',
    ];
}

// Test Cat1 users
echo "=== CAT1 USERS (Never Paid - Should be BLOCKED) ===\n";
$cat1Users = User::where(function($q) {
        $q->where('has_paid_verification_fee', false)
          ->orWhereNull('has_paid_verification_fee');
    })
    ->latest()
    ->limit(3)
    ->get();

foreach ($cat1Users as $user) {
    $result = simulatePaywallState($user);
    echo "\nUser: {$result['user']}\n";
    echo "  has_paid: " . ($result['has_paid'] ? 'YES' : 'NO') . "\n";
    echo "  category: {$result['category']}\n";
    echo "  access_active: " . ($result['access_active'] ? 'TRUE' : 'FALSE') . "\n";
    echo "  >>> CAN WATCH VIDEO: {$result['CAN_WATCH_VIDEO']}\n";
}

echo "\n\n=== CAT2 USERS (Paid + Real Autopay OR Admin Forced) ===\n";

// prasad specifically
$prasad = User::where('phone_number', '+917418676356')->first();
if ($prasad) {
    $result = simulatePaywallState($prasad);
    echo "\nUser: {$result['user']}\n";
    echo "  has_paid: " . ($result['has_paid'] ? 'YES' : 'NO') . "\n";
    echo "  subscription_status: " . ($result['subscription_status'] ?? 'none') . "\n";
    echo "  razorpay_sub_id: " . ($result['razorpay_sub_id'] ?? 'NULL') . "\n";
    echo "  auto_renew: " . ($result['auto_renew'] ? 'YES' : 'NO') . "\n";
    echo "  is_real_autopay: " . ($result['is_real_autopay'] ? 'YES' : 'NO') . "\n";
    echo "  force_premium: " . ($result['force_premium'] ? 'YES' : 'NO') . "\n";
    echo "  category: {$result['category']}\n";
    echo "  access_active: " . ($result['access_active'] ? 'TRUE' : 'FALSE') . "\n";
    echo "  >>> CAN WATCH VIDEO: {$result['CAN_WATCH_VIDEO']}\n";
}

echo "\n\n=== CAT3 USERS (Paid, within 7 days) ===\n";
$cat3Users = User::where('has_paid_verification_fee', true)
    ->latest()
    ->limit(3)
    ->get();

foreach ($cat3Users as $user) {
    $result = simulatePaywallState($user);
    echo "\nUser: {$result['user']}\n";
    echo "  has_paid: " . ($result['has_paid'] ? 'YES' : 'NO') . "\n";
    echo "  is_real_autopay: " . ($result['is_real_autopay'] ? 'YES' : 'NO') . "\n";
    echo "  category: {$result['category']}\n";
    echo "  access_active: " . ($result['access_active'] ? 'TRUE' : 'FALSE') . "\n";
    echo "  access_ends_at: " . ($result['access_ends_at'] ?? 'N/A') . "\n";
    echo "  >>> CAN WATCH VIDEO: {$result['CAN_WATCH_VIDEO']}\n";
}

echo "\n\n=== SPECIFIC: Campa Ajith ===\n";
$campa = User::where('phone_number', '+917406309108')->first();
if ($campa) {
    $result = simulatePaywallState($campa);
    echo "User: {$result['user']}\n";
    echo "  has_paid: " . ($result['has_paid'] ? 'YES' : 'NO') . "\n";
    echo "  razorpay_sub_id: " . ($result['razorpay_sub_id'] ?? 'NULL') . "\n";
    echo "  category: {$result['category']}\n";
    echo "  >>> CAN WATCH VIDEO: {$result['CAN_WATCH_VIDEO']}\n";
}
