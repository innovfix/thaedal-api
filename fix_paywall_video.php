<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PaymentSetting;

$settings = PaymentSetting::first();

echo "=== CURRENT PAYWALL VIDEO SETTINGS ===\n";
echo "Type: " . ($settings->paywall_video_type ?? 'NULL') . "\n";
echo "URL: " . ($settings->paywall_video_url ?? 'NULL') . "\n";
echo "Path: " . ($settings->paywall_video_path ?? 'NULL') . "\n";

// Check what videos exist
echo "\n=== AVAILABLE VIDEOS ===\n";
$videosDir = storage_path('app/public/paywall_videos');
if (is_dir($videosDir)) {
    $files = scandir($videosDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $size = filesize($videosDir . '/' . $file);
            echo "- $file (" . round($size / 1024 / 1024, 2) . " MB)\n";
        }
    }
} else {
    echo "Videos directory not found\n";
}

// Fix the paywall video - use the largest video file
if (is_dir($videosDir)) {
    $files = glob($videosDir . '/*.mp4');
    if (count($files) > 0) {
        // Find the largest file (likely the main video)
        $largest = '';
        $largestSize = 0;
        foreach ($files as $file) {
            $size = filesize($file);
            if ($size > $largestSize) {
                $largestSize = $size;
                $largest = basename($file);
            }
        }
        
        if ($largest) {
            $settings->update([
                'paywall_video_type' => 'file',
                'paywall_video_path' => 'paywall_videos/' . $largest,
            ]);
            echo "\n✅ Fixed! Set paywall video to: paywall_videos/$largest\n";
            
            // Verify
            $url = $settings->paywallVideoUrl();
            echo "Video URL: $url\n";
        }
    }
}
