<?php
/**
 * Fix users who paid ₹2 via Razorpay but weren't marked in the database
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== FIXING USERS WHO PAID ₹2 BUT NOT MARKED ===\n\n";

// Users who paid ₹2 according to Razorpay (from the API fetch)
$paidUsers = [
    '+916381622609' => ['name' => 'yuvan', 'paid_at' => '2026-01-22 10:58:00'],
    '+917904573326' => ['name' => 'sudharsan', 'paid_at' => '2026-01-21 21:30:00'],
    '+918129037565' => ['name' => 'VIJIVIJAYARAJ', 'paid_at' => '2026-01-21 21:28:00'],
    '+919790612583' => ['name' => 'dharini', 'paid_at' => '2026-01-21 21:11:00'],
    '+918124487260' => ['name' => 'muthumari', 'paid_at' => '2026-01-21 21:04:00'],
    '+919566720963' => ['name' => 'PASUPATHI', 'paid_at' => '2026-01-21 18:48:00'],
    '+919488327288' => ['name' => 'Senthilkumar', 'paid_at' => '2026-01-21 17:29:00'],
    '+919187056704' => ['name' => 'Dhanu', 'paid_at' => '2026-01-09 15:51:00'],
    '+919361880076' => ['name' => 'nknjb', 'paid_at' => '2026-01-11 19:06:00'], // already marked but ensure
    '+917418676356' => ['name' => 'prasad', 'paid_at' => '2026-01-01 19:19:00'], // already marked but ensure
];

$fixed = 0;
$alreadyOk = 0;
$notFound = 0;

foreach ($paidUsers as $phone => $data) {
    // Try different phone formats
    $user = User::where('phone_number', $phone)
        ->orWhere('phone_number', '+91' . ltrim($phone, '+91'))
        ->orWhere('phone_number', ltrim($phone, '+'))
        ->orWhere('phone_number', '91' . substr($phone, -10))
        ->first();
    
    if (!$user) {
        // Try just the last 10 digits
        $last10 = substr(preg_replace('/[^0-9]/', '', $phone), -10);
        $user = User::where('phone_number', 'like', '%' . $last10)->first();
    }
    
    if ($user) {
        if ($user->has_paid_verification_fee) {
            echo "✅ {$data['name']} ({$phone}) - Already marked as paid\n";
            $alreadyOk++;
        } else {
            $user->has_paid_verification_fee = true;
            $user->verification_fee_paid_at = $data['paid_at'];
            $user->save();
            echo "🔧 FIXED: {$data['name']} ({$phone}) - Now marked as paid\n";
            $fixed++;
        }
    } else {
        echo "❌ NOT FOUND: {$data['name']} ({$phone})\n";
        $notFound++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Fixed: {$fixed}\n";
echo "Already OK: {$alreadyOk}\n";
echo "Not found: {$notFound}\n";

// Verify
echo "\n=== VERIFICATION ===\n";
$paidCount = User::where('has_paid_verification_fee', true)->count();
echo "Total users with has_paid_verification_fee=true: {$paidCount}\n";
