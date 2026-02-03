<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== CHECKING DATABASE VALUES ===\n\n";

// Check yuvan
$yuvan = User::where('phone_number', '+916381622609')->first();
echo "YUVAN (+916381622609):\n";
echo "  has_paid_verification_fee: " . var_export($yuvan->has_paid_verification_fee, true) . "\n";
echo "  verification_fee_paid_at: " . ($yuvan->verification_fee_paid_at ?? 'NULL') . "\n";
echo "  is_subscribed: " . var_export($yuvan->is_subscribed, true) . "\n";

// Check prasad (Cat2)
$prasad = User::where('phone_number', '+917418676356')->first();
echo "\nPRASAD (+917418676356):\n";
echo "  has_paid_verification_fee: " . var_export($prasad->has_paid_verification_fee, true) . "\n";
echo "  is_subscribed: " . var_export($prasad->is_subscribed, true) . "\n";
$sub = $prasad->subscriptions()->latest()->first();
if ($sub) {
    echo "  Subscription:\n";
    echo "    status: {$sub->status}\n";
    echo "    ends_at: " . ($sub->ends_at ? $sub->ends_at->format('Y-m-d H:i') : 'NULL') . "\n";
    echo "    now: " . now()->format('Y-m-d H:i') . "\n";
    echo "    ends_at > now: " . ($sub->ends_at && $sub->ends_at->gt(now()) ? 'YES' : 'NO') . "\n";
}

// Check Campa Ajith
$campa = User::where('phone_number', '+917406309108')->first();
echo "\nCAMPA AJITH (+917406309108):\n";
echo "  has_paid_verification_fee: " . var_export($campa->has_paid_verification_fee, true) . "\n";
echo "  verification_fee_paid_at: " . ($campa->verification_fee_paid_at ?? 'NULL') . "\n";

// Count users by payment status
echo "\n=== USER COUNTS ===\n";
echo "Total users: " . User::count() . "\n";
echo "Users with has_paid_verification_fee = true: " . User::where('has_paid_verification_fee', true)->count() . "\n";
echo "Users with has_paid_verification_fee = false/null: " . User::where(function($q) {
    $q->where('has_paid_verification_fee', false)->orWhereNull('has_paid_verification_fee');
})->count() . "\n";
