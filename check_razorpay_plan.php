<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SubscriptionPlan;

$keyId = config('services.razorpay.key_id');
$keySecret = config('services.razorpay.key_secret');

echo "=== LOCAL DATABASE PLANS ===\n";
$plans = SubscriptionPlan::all();
foreach ($plans as $plan) {
    echo "Plan: {$plan->name}\n";
    echo "  ID: {$plan->id}\n";
    echo "  Price: ₹{$plan->price}\n";
    echo "  Razorpay Plan ID: " . ($plan->razorpay_plan_id ?? 'NOT SET') . "\n";
    echo "\n";
}

echo "=== RAZORPAY PLANS ===\n";
// Fetch plans from Razorpay
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/plans");
curl_setopt($ch, CURLOPT_USERPWD, "$keyId:$keySecret");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['items'])) {
    foreach ($data['items'] as $plan) {
        $amount = ($plan['item']['amount'] ?? 0) / 100;
        echo "Razorpay Plan: " . ($plan['item']['name'] ?? 'Unknown') . "\n";
        echo "  Plan ID: {$plan['id']}\n";
        echo "  Amount: ₹{$amount}\n";
        echo "  Period: " . ($plan['period'] ?? 'N/A') . "\n";
        echo "  Interval: " . ($plan['interval'] ?? 'N/A') . "\n";
        echo "  Status: " . ($plan['item']['active'] ? 'Active' : 'Inactive') . "\n";
        echo "\n";
    }
} else {
    echo "Error fetching plans: " . json_encode($data) . "\n";
}
