<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subscription;

echo "=== SUBSCRIPTION STATS ===\n";
echo "With Razorpay ID: " . Subscription::whereNotNull('razorpay_subscription_id')->count() . "\n";
echo "Without Razorpay ID (NULL): " . Subscription::whereNull('razorpay_subscription_id')->count() . "\n";
echo "\n";

echo "=== SUBSCRIPTIONS WITH RAZORPAY ID ===\n";
$withRzp = Subscription::whereNotNull('razorpay_subscription_id')->get();
foreach ($withRzp as $s) {
    echo "ID: " . substr($s->id, 0, 8) . " | Status: " . $s->status . " | RZP: " . $s->razorpay_subscription_id . "\n";
}

echo "\n=== RECENT 10 SUBSCRIPTIONS (NULL) ===\n";
$subs = Subscription::whereNull('razorpay_subscription_id')->latest()->limit(10)->get();
foreach ($subs as $s) {
    echo "ID: " . substr($s->id, 0, 8) . " | Status: " . $s->status . " | Created: " . $s->created_at->format('Y-m-d H:i') . "\n";
}
