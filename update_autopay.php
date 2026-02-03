<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PaymentSetting;

$s = PaymentSetting::first();

echo "=== BEFORE ===\n";
echo "Verification Fee: ₹" . ($s->verification_fee_amount_paise / 100) . "\n";
echo "Autopay Amount: ₹" . ($s->autopay_amount_paise / 100) . "\n";

// Update autopay to ₹299 (29900 paise)
$s->update([
    'autopay_amount_paise' => 29900
]);

echo "\n=== AFTER ===\n";
$s->refresh();
echo "Verification Fee: ₹" . ($s->verification_fee_amount_paise / 100) . "\n";
echo "Autopay Amount: ₹" . ($s->autopay_amount_paise / 100) . "\n";

echo "\n✅ Autopay amount updated to ₹299!\n";
