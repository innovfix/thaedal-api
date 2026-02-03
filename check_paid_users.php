<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Payment;
use Illuminate\Support\Facades\Schema;

echo "=== CHECKING PAYMENT STATUS ===\n\n";

// Check if has_paid_verification_fee column exists
echo "1. Checking if 'has_paid_verification_fee' column exists on users table...\n";
if (Schema::hasColumn('users', 'has_paid_verification_fee')) {
    echo "   ✅ Column exists\n";
} else {
    echo "   ❌ Column MISSING! This is why everyone shows as Free.\n";
    echo "   Need to add this column to users table.\n\n";
}

// Check if verification_fee_paid_at column exists
echo "\n2. Checking if 'verification_fee_paid_at' column exists on users table...\n";
if (Schema::hasColumn('users', 'verification_fee_paid_at')) {
    echo "   ✅ Column exists\n";
} else {
    echo "   ❌ Column MISSING!\n";
}

// Check successful payments
echo "\n3. Checking successful payments in database...\n";
$successPayments = Payment::where('status', 'success')->get();
echo "   Found {$successPayments->count()} successful payment(s):\n";

foreach ($successPayments as $payment) {
    $user = $payment->user;
    echo "\n   Payment ID: {$payment->id}\n";
    echo "   User: " . ($user ? $user->name . " ({$user->phone_number})" : "N/A") . "\n";
    echo "   Amount: ₹" . number_format($payment->amount, 2) . "\n";
    echo "   Paid at: " . ($payment->paid_at ?? $payment->created_at) . "\n";
    echo "   Razorpay ID: " . ($payment->razorpay_payment_id ?? 'N/A') . "\n";
    
    if ($user) {
        echo "   User's has_paid_verification_fee: " . (($user->has_paid_verification_fee ?? false) ? 'YES' : 'NO') . "\n";
        echo "   User's is_subscribed: " . ($user->is_subscribed ? 'YES' : 'NO') . "\n";
    }
}

// List users who should have paid based on payments
echo "\n\n4. Users who SHOULD be marked as paid (have successful payments):\n";
$paidUserIds = Payment::where('status', 'success')->pluck('user_id')->unique();
foreach ($paidUserIds as $userId) {
    $user = User::find($userId);
    if ($user) {
        $hasPaidFlag = $user->has_paid_verification_fee ?? false;
        echo "   - {$user->name} ({$user->phone_number}): has_paid_verification_fee = " . ($hasPaidFlag ? 'YES ✅' : 'NO ❌ (NEEDS FIX)') . "\n";
    }
}

echo "\n=== END ===\n";
