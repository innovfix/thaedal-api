<?php
// Check OTP delivery - how many were verified recently
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Otp;
use Carbon\Carbon;

$hours = $argv[1] ?? 3;

$sent = Otp::where('created_at', '>', Carbon::now()->subHours($hours))->count();
$verified = Otp::where('is_used', true)->where('created_at', '>', Carbon::now()->subHours($hours))->count();

$rate = $sent > 0 ? round(($verified / $sent) * 100, 1) : 0;

echo json_encode([
    'period_hours' => (int)$hours,
    'otps_sent' => $sent,
    'otps_verified' => $verified,
    'delivery_rate' => $rate . '%',
    'status' => $rate > 50 ? 'healthy' : ($rate > 20 ? 'degraded' : 'critical'),
]);
