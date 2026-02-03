<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Direct DB update to bypass fillable
DB::table('subscription_plans')
    ->where('name', 'Monthly Plan')
    ->update([
        'price' => 299,
        'razorpay_plan_id' => 'plan_S6ur7PZEqmVkxu'
    ]);

// Verify
$plan = DB::table('subscription_plans')->where('name', 'Monthly Plan')->first();
echo "=== UPDATED ===\n";
echo "Plan: {$plan->name}\n";
echo "Price: ₹{$plan->price}\n";
echo "Razorpay Plan ID: {$plan->razorpay_plan_id}\n";

echo "\n✅ Done! Now using ₹299 Razorpay plan (plan_S6ur7PZEqmVkxu)\n";
