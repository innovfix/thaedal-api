<?php
/**
 * SYNC all Razorpay subscriptions and update local database
 * Also identify failed autopay and take action
 */
require __DIR__ . "/vendor/autoload.php";
$app = require_once __DIR__ . "/bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$keyId = config("services.razorpay.key_id");
$keySecret = config("services.razorpay.key_secret");
$api = new Razorpay\Api\Api($keyId, $keySecret);

echo "=== SYNCING RAZORPAY SUBSCRIPTIONS ===\n\n";

// Get all local subscriptions with Razorpay IDs
$localSubs = Subscription::whereNotNull('razorpay_subscription_id')
    ->with('user')
    ->get();

$stats = [
    'total' => 0,
    'synced' => 0,
    'expired' => 0,
    'active' => 0,
    'pending' => 0,
    'halted' => 0,
    'authenticated' => 0,
    'cancelled' => 0,
    'errors' => 0,
];

foreach ($localSubs as $sub) {
    $stats['total']++;
    
    try {
        $rzpSub = $api->subscription->fetch($sub->razorpay_subscription_id);
        $rzpStatus = $rzpSub->status;
        $paidCount = $rzpSub->paid_count ?? 0;
        $authAttempts = $rzpSub->auth_attempts ?? 0;
        
        // Update local status to match Razorpay
        $newLocalStatus = $sub->status;
        $shouldUpdateUser = false;
        
        switch ($rzpStatus) {
            case 'active':
                $stats['active']++;
                if ($paidCount > 0) {
                    $newLocalStatus = 'active';
                    $shouldUpdateUser = true;
                } else {
                    // Active but no payments yet - trial
                    $newLocalStatus = 'trial';
                }
                break;
                
            case 'authenticated':
                $stats['authenticated']++;
                $newLocalStatus = 'trial';
                break;
                
            case 'pending':
                $stats['pending']++;
                // Autopay is failing
                if ($authAttempts > 3) {
                    $newLocalStatus = 'expired';
                }
                break;
                
            case 'halted':
                $stats['halted']++;
                $newLocalStatus = 'expired';
                break;
                
            case 'expired':
            case 'completed':
                $stats['expired']++;
                $newLocalStatus = 'expired';
                break;
                
            case 'cancelled':
                $stats['cancelled']++;
                $newLocalStatus = 'cancelled';
                break;
        }
        
        // Update local subscription
        if ($sub->status !== $newLocalStatus) {
            $sub->update(['status' => $newLocalStatus]);
            echo "[UPDATED] {$sub->user->phone_number}: {$sub->status} -> {$newLocalStatus} (Razorpay: {$rzpStatus})\n";
        }
        
        // Update user subscription status
        if ($rzpStatus === 'expired' || $rzpStatus === 'halted' || $rzpStatus === 'cancelled') {
            // Check if user has ANY other active subscription
            $hasOther = Subscription::where('user_id', $sub->user_id)
                ->where('id', '!=', $sub->id)
                ->whereIn('status', ['active', 'trial'])
                ->exists();
            
            if (!$hasOther) {
                $sub->user->update(['is_subscribed' => false]);
                echo "[USER DOWNGRADED] {$sub->user->phone_number} -> FREE\n";
            }
        }
        
        $stats['synced']++;
        
    } catch (Exception $e) {
        $stats['errors']++;
        echo "[ERROR] {$sub->razorpay_subscription_id}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== SYNC COMPLETE ===\n";
echo "Total: {$stats['total']}\n";
echo "Synced: {$stats['synced']}\n";
echo "Active: {$stats['active']}\n";
echo "Authenticated: {$stats['authenticated']}\n";
echo "Pending: {$stats['pending']}\n";
echo "Halted: {$stats['halted']}\n";
echo "Expired: {$stats['expired']}\n";
echo "Cancelled: {$stats['cancelled']}\n";
echo "Errors: {$stats['errors']}\n";

// Summary of autopay status
echo "\n=== AUTOPAY SUMMARY ===\n";
$autopayOn = Subscription::where('auto_renew', true)
    ->whereIn('status', ['active', 'trial'])
    ->whereNotNull('razorpay_subscription_id')
    ->count();
echo "Autopay Enabled & Active: $autopayOn\n";

$autopayOff = User::where('has_paid_verification_fee', true)
    ->where('is_subscribed', false)
    ->count();
echo "Paid Rs 2 but NOT Premium: $autopayOff\n";
