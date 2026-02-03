<?php
require __DIR__ . "/vendor/autoload.php";
$app = require_once __DIR__ . "/bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

$keyId = config("services.razorpay.key_id");
$keySecret = config("services.razorpay.key_secret");
$api = new Razorpay\Api\Api($keyId, $keySecret);

// Check subscriptions that should be charging
$subs = [
    "sub_S3NC3jZSNwPHV6",  // lingu - 12 days overdue
    "sub_S7fGVKVSgPR8p1",  // AROKIYASAMY - active
    "sub_S8BW2f7ak8X0mo",  // sumithra - active
];

foreach ($subs as $subId) {
    try {
        $sub = $api->subscription->fetch($subId);
        echo "\n=== $subId ===\n";
        echo "Status: " . $sub->status . "\n";
        echo "Paid Count: " . ($sub->paid_count ?? 0) . "\n";
        echo "Auth Attempts: " . ($sub->auth_attempts ?? 0) . "\n";
        echo "Charge At: " . ($sub->charge_at ? date("Y-m-d H:i:s", $sub->charge_at) : "NULL") . "\n";
        echo "Current End: " . ($sub->current_end ? date("Y-m-d H:i:s", $sub->current_end) : "NULL") . "\n";
    } catch (Exception $e) {
        echo "$subId: ERROR - " . $e->getMessage() . "\n";
    }
}
