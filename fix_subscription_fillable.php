<?php
/**
 * FIX: Add razorpay_subscription_id and other fields to Subscription model fillable
 */

$modelPath = '/var/www/thaedal/api/app/Models/Subscription.php';
$content = file_get_contents($modelPath);

// Check if razorpay_subscription_id is already in fillable
if (strpos($content, "'razorpay_subscription_id'") !== false && strpos($content, 'fillable') !== false) {
    // Check if it's inside fillable
    $fillablePos = strpos($content, '$fillable');
    $nextBracket = strpos($content, '];', $fillablePos);
    $fillableSection = substr($content, $fillablePos, $nextBracket - $fillablePos);
    if (strpos($fillableSection, "'razorpay_subscription_id'") !== false) {
        echo "razorpay_subscription_id is already in \$fillable array.\n";
        exit(0);
    }
}

// Replace the fillable array
$oldFillable = <<<'PHP'
    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'is_trial',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'auto_renew',
        'payment_method_id',
        'next_billing_date',
        'cancelled_at',
        'cancellation_reason',
    ];
PHP;

$newFillable = <<<'PHP'
    protected $fillable = [
        'user_id',
        'plan_id',
        'razorpay_subscription_id',
        'razorpay_customer_id',
        'razorpay_payment_id',
        'status',
        'is_trial',
        'trial_days',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'auto_renew',
        'payment_method_id',
        'next_billing_date',
        'cancelled_at',
        'cancellation_reason',
    ];
PHP;

$newContent = str_replace($oldFillable, $newFillable, $content);

if ($newContent === $content) {
    echo "WARNING: Could not find the exact fillable array to replace. Trying alternative...\n";
    
    // Try to insert after 'plan_id',
    $newContent = preg_replace(
        "/('plan_id',)\s*\n\s*('status',)/",
        "$1\n        'razorpay_subscription_id',\n        'razorpay_customer_id',\n        'razorpay_payment_id',\n        $2",
        $content
    );
    
    if ($newContent === $content) {
        echo "ERROR: Could not update fillable array. Please update manually.\n";
        exit(1);
    }
}

file_put_contents($modelPath, $newContent);
echo "✅ Fixed Subscription model - added razorpay_subscription_id to \$fillable\n";

// Verify
$verifyContent = file_get_contents($modelPath);
if (strpos($verifyContent, "'razorpay_subscription_id'") !== false) {
    echo "✅ Verified: razorpay_subscription_id is now in fillable array\n";
} else {
    echo "❌ ERROR: razorpay_subscription_id was not added!\n";
}
