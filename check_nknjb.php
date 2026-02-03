<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

// Check user who paid ₹2
$user = User::where('phone_number', '+919361880076')->first();
if ($user) {
    echo "User who paid ₹2:\n";
    echo "  Name: {$user->name}\n";
    echo "  Phone: {$user->phone_number}\n";
    echo "  has_paid_verification_fee: " . ($user->has_paid_verification_fee ? 'YES' : 'NO') . "\n";
    echo "  is_subscribed: " . ($user->is_subscribed ? 'YES' : 'NO') . "\n";
    
    $latest = $user->subscriptions()->latest()->first();
    if ($latest) {
        echo "  Latest sub status: {$latest->status}\n";
        echo "  Latest sub auto_renew: " . ($latest->auto_renew ? 'YES' : 'NO') . "\n";
    }
}
