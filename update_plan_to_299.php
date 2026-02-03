<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SubscriptionPlan;

// Update Monthly Plan to use the new ₹299 Razorpay plan
$plan = SubscriptionPlan::where('name', 'Monthly Plan')->first();

if ($plan) {
    echo "=== BEFORE ===\n";
    echo "Plan: {$plan->name}\n";
    echo "Price: ₹{$plan->price}\n";
    echo "Razorpay Plan ID: {$plan->razorpay_plan_id}\n";
    
    // Update to new ₹299 plan
    $plan->update([
        'price' => 299,
        'razorpay_plan_id' => 'plan_S6ur7PZEqmVkxu'
    ]);
    
    $plan->refresh();
    
    echo "\n=== AFTER ===\n";
    echo "Plan: {$plan->name}\n";
    echo "Price: ₹{$plan->price}\n";
    echo "Razorpay Plan ID: {$plan->razorpay_plan_id}\n";
    
    echo "\n✅ Monthly Plan updated to ₹299 with new Razorpay plan!\n";
} else {
    echo "Monthly Plan not found!\n";
}
