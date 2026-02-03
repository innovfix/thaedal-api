<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PaymentSetting;

$s = PaymentSetting::first();

echo "=== PAYWALL VIDEO SETTINGS ===\n";
echo "Type: " . ($s->paywall_video_type ?? 'NULL') . "\n";
echo "URL: " . ($s->paywall_video_url ?? 'NULL') . "\n";
echo "Path: " . ($s->paywall_video_path ?? 'NULL') . "\n";
echo "Full URL: " . ($s->paywallVideoUrl() ?? 'NULL') . "\n";

// Check files in paywall_videos folder
echo "\n=== FILES IN PAYWALL_VIDEOS ===\n";
$dir = storage_path('app/public/paywall_videos');
$files = glob($dir . '/*');
foreach ($files as $file) {
    $name = basename($file);
    $size = round(filesize($file) / 1024 / 1024, 2);
    $time = date('Y-m-d H:i:s', filemtime($file));
    echo "- $name ($size MB) - Modified: $time\n";
}
