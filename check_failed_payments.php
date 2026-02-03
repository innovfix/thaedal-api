<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$keyId = config('services.razorpay.key_id');
$keySecret = config('services.razorpay.key_secret');

$paymentIds = [
    'pay_S6sIXTMXgfnpHP',
    'pay_S6sHhgBb6OeWxg',
    'pay_S6sFyw6leA6b6K',
    'pay_S6sEwdMmrOy7Gm',
];

echo "=== CHECKING FAILED PAYMENTS ===\n\n";

foreach ($paymentIds as $paymentId) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/payments/$paymentId");
    curl_setopt($ch, CURLOPT_USERPWD, "$keyId:$keySecret");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    echo "Payment: $paymentId\n";
    echo "  Status: " . ($data['status'] ?? 'unknown') . "\n";
    echo "  Amount: ₹" . (($data['amount'] ?? 0) / 100) . "\n";
    echo "  Method: " . ($data['method'] ?? 'unknown') . "\n";
    echo "  Error Code: " . ($data['error_code'] ?? 'none') . "\n";
    echo "  Error Reason: " . ($data['error_reason'] ?? 'none') . "\n";
    echo "  Error Description: " . ($data['error_description'] ?? 'none') . "\n";
    
    if (isset($data['error_source'])) {
        echo "  Error Source: " . $data['error_source'] . "\n";
    }
    if (isset($data['error_step'])) {
        echo "  Error Step: " . $data['error_step'] . "\n";
    }
    
    echo "\n";
}
