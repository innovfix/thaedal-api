<?php
// Fix RazorpayWebhookController and SubscriptionController directly

$webhookPath = '/var/www/thaedal/api/app/Http/Controllers/Api/RazorpayWebhookController.php';
$subPath = '/var/www/thaedal/api/app/Http/Controllers/Api/SubscriptionController.php';
$payControllerPath = '/var/www/thaedal/api/app/Http/Controllers/Api/PaymentController.php';

// Fix RazorpayWebhookController
$webhook = file_get_contents($webhookPath);
$webhook = str_replace(
    "\$subscription->user->update(['is_subscribed' => true]);",
    "\$subscription->user->forceFill(['is_subscribed' => true, 'has_paid_verification_fee' => true, 'verification_fee_paid_at' => \$subscription->user->verification_fee_paid_at ?? now()])->save();",
    $webhook
);
file_put_contents($webhookPath, $webhook);
echo "Fixed RazorpayWebhookController\n";

// Fix SubscriptionController
$sub = file_get_contents($subPath);
$sub = str_replace(
    "\$user->update(['is_subscribed' => true]);",
    "\$user->forceFill(['is_subscribed' => true, 'has_paid_verification_fee' => true, 'verification_fee_paid_at' => \$user->verification_fee_paid_at ?? now()])->save();",
    $sub
);
file_put_contents($subPath, $sub);
echo "Fixed SubscriptionController\n";

// Fix PaymentController
$pay = file_get_contents($payControllerPath);
$pay = str_replace(
    "\$user->update(['is_subscribed' => true]);",
    "\$user->forceFill(['is_subscribed' => true, 'has_paid_verification_fee' => true, 'verification_fee_paid_at' => \$user->verification_fee_paid_at ?? now()])->save();",
    $pay
);
file_put_contents($payControllerPath, $pay);
echo "Fixed PaymentController\n";

echo "\nDone! All controllers now set has_paid_verification_fee\n";
