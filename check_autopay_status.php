<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subscription;

echo "=== CHECKING AUTOPAY STATUS FOR PAID USERS ===\n\n";

// Get users who paid
$paidUsers = User::where('has_paid_verification_fee', true)
    ->with(['subscriptions' => function($q) {
        $q->latest('created_at');
    }])
    ->get();

echo "Found " . $paidUsers->count() . " users who paid ₹2:\n\n";

foreach ($paidUsers as $user) {
    echo "User: {$user->name} ({$user->phone_number})\n";
    echo "  has_paid_verification_fee: YES\n";
    
    $latest = $user->subscriptions->first();
    
    if ($latest) {
        echo "  Latest Subscription:\n";
        echo "    - ID: {$latest->id}\n";
        echo "    - status: {$latest->status}\n";
        echo "    - is_trial: " . ($latest->is_trial ? 'YES' : 'NO') . "\n";
        echo "    - auto_renew: " . ($latest->auto_renew ? 'YES (Autopay ON)' : 'NO (Autopay OFF)') . "\n";
        echo "    - ends_at: " . ($latest->ends_at ? $latest->ends_at->format('Y-m-d H:i') : 'NULL') . "\n";
        echo "    - razorpay_subscription_id: " . ($latest->razorpay_subscription_id ?? 'NULL') . "\n";
        echo "    - created_at: " . $latest->created_at->format('Y-m-d H:i') . "\n";
    } else {
        echo "  No subscription record found!\n";
    }
    
    // Check all subscriptions for this user
    $allSubs = $user->subscriptions;
    if ($allSubs->count() > 1) {
        echo "  All subscriptions ({$allSubs->count()}):\n";
        foreach ($allSubs as $i => $sub) {
            echo "    [{$i}] status={$sub->status}, auto_renew=" . ($sub->auto_renew ? 'YES' : 'NO') . ", created={$sub->created_at->format('Y-m-d H:i')}\n";
        }
    }
    
    echo "\n";
}

echo "\n=== SUBSCRIPTION TABLE STRUCTURE ===\n";
$columns = \Illuminate\Support\Facades\Schema::getColumnListing('subscriptions');
echo "Columns: " . implode(', ', $columns) . "\n";

echo "\n=== RAW SUBSCRIPTION DATA (last 5) ===\n";
$subs = Subscription::latest()->limit(5)->get();
foreach ($subs as $sub) {
    echo json_encode([
        'id' => substr($sub->id, 0, 8),
        'user_id' => substr($sub->user_id, 0, 8),
        'status' => $sub->status,
        'is_trial' => $sub->is_trial,
        'auto_renew' => $sub->auto_renew,
        'razorpay_sub_id' => $sub->razorpay_subscription_id ? substr($sub->razorpay_subscription_id, 0, 15) : null,
    ], JSON_PRETTY_PRINT) . "\n";
}
