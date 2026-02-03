<?php
// Check OTP delivery status
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = $argv[1] ?? '+919791625610';
$otp = App\Models\Otp::where('phone_number', $phone)->latest()->first();

if ($otp) {
    echo json_encode([
        'found' => true,
        'otp' => $otp->otp,
        'created_at' => $otp->created_at->toIso8601String(),
        'expires_at' => $otp->expires_at->toIso8601String(),
        'is_used' => $otp->is_used,
        'is_valid' => $otp->isValid(),
    ]);
} else {
    echo json_encode(['found' => false]);
}
