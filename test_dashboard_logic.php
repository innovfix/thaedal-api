<?php
/**
 * Test the exact dashboard logic to verify numbers.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Subscription;
use App\Models\Payment;
use App\Models\User;

echo "=== TESTING DASHBOARD LOGIC ===\n\n";

// Valid subscriptions query
$validSubQuery = Subscription::query()
    ->whereIn('status', ['active', 'trial'])
    ->where(function ($q) {
        $q->whereNull('ends_at')
            ->orWhere('ends_at', '>', now());
    });

$activeSubscriptionUsers = (int) $validSubQuery->distinct('user_id')->count('user_id');
echo "activeSubscriptionUsers (valid subs): {$activeSubscriptionUsers}\n";

// Admin forced premium users (is_subscribed=true but no valid sub)
$adminForcedPremiumUsers = (int) User::query()
    ->where('is_subscribed', true)
    ->whereDoesntHave('subscriptions', function ($q) {
        $q->whereIn('status', ['active', 'trial'])
            ->where(function ($q2) {
                $q2->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });
    })
    ->count();
echo "adminForcedPremiumUsers: {$adminForcedPremiumUsers}\n";

$activeSubscriptions = $activeSubscriptionUsers + $adminForcedPremiumUsers;
echo "TOTAL Active Subscriptions: {$activeSubscriptions}\n\n";

// New subscriptions today
$newSubscriptionsToday = Subscription::query()
    ->whereIn('status', ['active', 'trial'])
    ->whereDate('created_at', now()->toDateString())
    ->count();
echo "New subscriptions today: {$newSubscriptionsToday}\n";

// Check users with is_subscribed=true
echo "\n--- Users with is_subscribed=true ---\n";
$subscribedUsers = User::where('is_subscribed', true)->get(['id', 'name', 'phone_number', 'is_subscribed']);
foreach ($subscribedUsers as $u) {
    echo "  {$u->id} | {$u->name} | {$u->phone_number}\n";
    
    // Check their subscriptions
    $subs = Subscription::where('user_id', $u->id)->get(['id', 'status', 'ends_at']);
    foreach ($subs as $s) {
        $expired = $s->ends_at && $s->ends_at < now() ? ' [EXPIRED]' : '';
        echo "    -> Sub: {$s->status} | ends: {$s->ends_at}{$expired}\n";
    }
}

echo "\n=== END ===\n";
