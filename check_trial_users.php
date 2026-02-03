<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subscription;

echo "=== TRIAL ACCESS USERS ANALYSIS ===\n\n";

// Get recent users with subscriptions
$users = User::with(['subscriptions' => function($q) {
    $q->latest('created_at');
}])
->whereHas('subscriptions')
->latest('created_at')
->limit(10)
->get();

foreach ($users as $user) {
    $latest = $user->subscriptions->first();
    
    echo "User: {$user->name} ({$user->phone_number})\n";
    echo "  - is_subscribed (admin flag): " . ($user->is_subscribed ? 'YES' : 'NO') . "\n";
    echo "  - has_paid_verification_fee: " . (($user->has_paid_verification_fee ?? false) ? 'YES' : 'NO') . "\n";
    
    if ($latest) {
        echo "  Latest Subscription:\n";
        echo "    - status: {$latest->status}\n";
        echo "    - is_trial: " . ($latest->is_trial ? 'YES' : 'NO') . "\n";
        echo "    - auto_renew: " . ($latest->auto_renew ? 'YES (Autopay ON)' : 'NO (Autopay OFF)') . "\n";
        echo "    - ends_at: " . ($latest->ends_at ? $latest->ends_at->format('Y-m-d H:i') : 'NULL') . "\n";
        echo "    - trial_ends_at: " . ($latest->trial_ends_at ? $latest->trial_ends_at->format('Y-m-d H:i') : 'NULL') . "\n";
        echo "    - created_at: " . $latest->created_at->format('Y-m-d H:i') . "\n";
        
        // Determine what category they would be in PaywallController
        $hasPaid = (bool)($user->has_paid_verification_fee ?? false);
        $isUsableStatus = in_array($latest->status, ['active', 'trial']) && optional($latest->ends_at)->gt(now());
        $autoRenewOn = (bool)($latest->auto_renew ?? false);
        
        if ($isUsableStatus && $autoRenewOn) {
            $cat = 'Cat2_AutopayOn (Premium)';
        } elseif (!$hasPaid) {
            $cat = 'Cat1_New (Never paid)';
        } else {
            $cat = 'Cat3_AutopayOffAfter2 (Paid but autopay off)';
        }
        echo "    - Paywall Category: {$cat}\n";
    } else {
        echo "  No subscription records\n";
    }
    echo "\n";
}

echo "=== LEGEND ===\n";
echo "Trial Access in Admin = User has subscription with is_trial=true OR status='trial'/'created'\n";
echo "Cat1_New = Never paid Rs 2\n";
echo "Cat2_AutopayOn = Paid + Autopay enabled = PREMIUM\n";
echo "Cat3_AutopayOffAfter2 = Paid Rs 2 but autopay is OFF\n";
