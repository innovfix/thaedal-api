<?php
/**
 * Fix payment webhook to properly set has_paid_verification_fee
 * 
 * The bug: When user pays ₹2, the payment goes through Razorpay but
 * has_paid_verification_fee is never set on the user.
 */

$base = '/var/www/thaedal/api';

// Backup files
$files = [
    'app/Models/Payment.php',
    'app/Http/Controllers/Api/RazorpayWebhookController.php',
    'app/Http/Controllers/Api/SubscriptionController.php',
];

foreach ($files as $file) {
    $path = $base . '/' . $file;
    if (file_exists($path)) {
        @copy($path, $path . '.bak.' . date('Ymd_His'));
    }
}

// 1) Update Payment model to mark user as paid when successful
$paymentPath = $base . '/app/Models/Payment.php';
$payment = file_get_contents($paymentPath);

// Replace markAsSuccessful method
$oldMethod = <<<'PHP'
    public function markAsSuccessful(string $paymentId, string $signature): void
    {
        $this->update([
            'status' => 'success',
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
            'paid_at' => now(),
        ]);
    }
PHP;

$newMethod = <<<'PHP'
    public function markAsSuccessful(string $paymentId, string $signature): void
    {
        $this->update([
            'status' => 'success',
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
            'paid_at' => now(),
        ]);

        // IMPORTANT: Mark user as having paid verification fee
        // This is needed for Cat1/Cat2/Cat3 categorization
        if ($this->user) {
            $this->user->forceFill([
                'has_paid_verification_fee' => true,
                'verification_fee_paid_at' => now(),
            ])->save();
            
            \Illuminate\Support\Facades\Log::info('User marked as paid verification fee', [
                'user_id' => $this->user_id,
                'payment_id' => $this->id,
                'amount' => $this->amount,
            ]);
        }
    }
PHP;

$payment = str_replace($oldMethod, $newMethod, $payment);
file_put_contents($paymentPath, $payment);
echo "✅ Updated Payment model - markAsSuccessful now sets has_paid_verification_fee\n";

// 2) Update RazorpayWebhookController to set has_paid_verification_fee
$webhookPath = $base . '/app/Http/Controllers/Api/RazorpayWebhookController.php';
$webhook = file_get_contents($webhookPath);

// Fix subscription.authenticated handler
$oldAuth = <<<'PHP'
            case 'subscription.authenticated':
                // User has authenticated the subscription (first Rs 2 paid)
                $subscription->update([
                    'status' => 'trial',
                ]);
                $subscription->user->update(['is_subscribed' => true]);
                Log::info('Subscription authenticated - trial started', ['subscription_id' => $subscription->id]);
                break;
PHP;

$newAuth = <<<'PHP'
            case 'subscription.authenticated':
                // User has authenticated the subscription (first Rs 2 paid)
                $subscription->update([
                    'status' => 'trial',
                ]);
                // Mark user as having paid AND set verification fee flag
                $subscription->user->forceFill([
                    'is_subscribed' => true,
                    'has_paid_verification_fee' => true,
                    'verification_fee_paid_at' => now(),
                ])->save();
                Log::info('Subscription authenticated - trial started, verification fee marked', ['subscription_id' => $subscription->id, 'user_id' => $subscription->user_id]);
                break;
PHP;

$webhook = str_replace($oldAuth, $newAuth, $webhook);

// Fix subscription.activated handler
$oldActivated = <<<'PHP'
            case 'subscription.activated':
                // Subscription is now active (after trial or immediate)
                $subscription->update([
                    'status' => 'active',
                    'is_trial' => false,
                ]);
                $subscription->user->update(['is_subscribed' => true]);
                Log::info('Subscription activated', ['subscription_id' => $subscription->id]);
                break;
PHP;

$newActivated = <<<'PHP'
            case 'subscription.activated':
                // Subscription is now active (after trial or immediate)
                $subscription->update([
                    'status' => 'active',
                    'is_trial' => false,
                ]);
                // Mark user as having paid AND set verification fee flag
                $subscription->user->forceFill([
                    'is_subscribed' => true,
                    'has_paid_verification_fee' => true,
                    'verification_fee_paid_at' => $subscription->user->verification_fee_paid_at ?? now(),
                ])->save();
                Log::info('Subscription activated', ['subscription_id' => $subscription->id]);
                break;
PHP;

$webhook = str_replace($oldActivated, $newActivated, $webhook);

// Fix subscription.charged handler
$oldCharged = <<<'PHP'
            case 'subscription.charged':
                // Recurring payment successful
                $currentEnd = $subscriptionEntity['current_end'] ?? null;
                $subscription->update([
                    'status' => 'active',
                    'ends_at' => $currentEnd ? date('Y-m-d H:i:s', $currentEnd) : $subscription->ends_at,
                    'next_billing_date' => $currentEnd ? date('Y-m-d H:i:s', $currentEnd) : null,
                ]);
                Log::info('Subscription charged', ['subscription_id' => $subscription->id]);
                break;
PHP;

$newCharged = <<<'PHP'
            case 'subscription.charged':
                // Recurring payment successful
                $currentEnd = $subscriptionEntity['current_end'] ?? null;
                $subscription->update([
                    'status' => 'active',
                    'ends_at' => $currentEnd ? date('Y-m-d H:i:s', $currentEnd) : $subscription->ends_at,
                    'next_billing_date' => $currentEnd ? date('Y-m-d H:i:s', $currentEnd) : null,
                ]);
                // Ensure verification fee flag is set
                if (!$subscription->user->has_paid_verification_fee) {
                    $subscription->user->forceFill([
                        'has_paid_verification_fee' => true,
                        'verification_fee_paid_at' => now(),
                    ])->save();
                }
                Log::info('Subscription charged', ['subscription_id' => $subscription->id]);
                break;
PHP;

$webhook = str_replace($oldCharged, $newCharged, $webhook);

// Fix handlePaymentEvent to also set has_paid_verification_fee
$oldPaymentEvent = <<<'PHP'
        if (($event === 'payment.captured' || $status === 'captured') && $status === 'captured') {
            $payment->update([
                'payment_method' => $method ?? $payment->payment_method,
            ]);

            if ($razorpayPaymentId) {
                $payment->markAsSuccessful($razorpayPaymentId, $payment->razorpay_signature ?? '');
            } else {
                $payment->update(['status' => 'success', 'paid_at' => now()]);
            }
        }
PHP;

$newPaymentEvent = <<<'PHP'
        if (($event === 'payment.captured' || $status === 'captured') && $status === 'captured') {
            $payment->update([
                'payment_method' => $method ?? $payment->payment_method,
            ]);

            if ($razorpayPaymentId) {
                $payment->markAsSuccessful($razorpayPaymentId, $payment->razorpay_signature ?? '');
            } else {
                $payment->update(['status' => 'success', 'paid_at' => now()]);
                // Also mark user as paid when updating directly
                if ($payment->user && !$payment->user->has_paid_verification_fee) {
                    $payment->user->forceFill([
                        'has_paid_verification_fee' => true,
                        'verification_fee_paid_at' => now(),
                    ])->save();
                }
            }
        }
PHP;

$webhook = str_replace($oldPaymentEvent, $newPaymentEvent, $webhook);

file_put_contents($webhookPath, $webhook);
echo "✅ Updated RazorpayWebhookController - all handlers now set has_paid_verification_fee\n";

// 3) Update SubscriptionController verifyPayment
$subControllerPath = $base . '/app/Http/Controllers/Api/SubscriptionController.php';
$subController = file_get_contents($subControllerPath);

// Fix verifyPayment
$oldVerify = <<<'PHP'
            // Mark user as subscribed
            $user->update(['is_subscribed' => true]);
PHP;

$newVerify = <<<'PHP'
            // Mark user as subscribed AND set verification fee flag
            $user->forceFill([
                'is_subscribed' => true,
                'has_paid_verification_fee' => true,
                'verification_fee_paid_at' => $user->verification_fee_paid_at ?? now(),
            ])->save();
PHP;

$subController = str_replace($oldVerify, $newVerify, $subController);
file_put_contents($subControllerPath, $subController);
echo "✅ Updated SubscriptionController - verifyPayment now sets has_paid_verification_fee\n";

// 4) Update PaymentController verify
$paymentControllerPath = $base . '/app/Http/Controllers/Api/PaymentController.php';
$paymentController = file_get_contents($paymentControllerPath);

$oldPayVerify = <<<'PHP'
                $payment->update(['subscription_id' => $subscription->id]);
                $user->update(['is_subscribed' => true]);
PHP;

$newPayVerify = <<<'PHP'
                $payment->update(['subscription_id' => $subscription->id]);
                // Mark user as subscribed AND set verification fee flag
                $user->forceFill([
                    'is_subscribed' => true,
                    'has_paid_verification_fee' => true,
                    'verification_fee_paid_at' => $user->verification_fee_paid_at ?? now(),
                ])->save();
PHP;

$paymentController = str_replace($oldPayVerify, $newPayVerify, $paymentController);
file_put_contents($paymentControllerPath, $paymentController);
echo "✅ Updated PaymentController - verify now sets has_paid_verification_fee\n";

echo "\n=== PAYMENT WEBHOOK FIX COMPLETE ===\n";
echo "Now all payment flows will correctly set has_paid_verification_fee = true\n";
echo "\nFlows fixed:\n";
echo "1. Payment::markAsSuccessful() - sets flag on user\n";
echo "2. RazorpayWebhookController - subscription.authenticated, .activated, .charged, payment.captured\n";
echo "3. SubscriptionController::verifyPayment() - sets flag\n";
echo "4. PaymentController::verify() - sets flag\n";
