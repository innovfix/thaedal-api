<?php
// Fix OneSignal tags to match what Android app actually sets
$path = '/var/www/thaedal/api/app/Services/OneSignalService.php';
$content = file_get_contents($path);

// Replace subscription_status with subscribed (Android uses: subscribed=true/false)
$content = str_replace(
    "'key' => 'subscription_status', 'relation' => '=', 'value' => 'premium'",
    "'key' => 'subscribed', 'relation' => '=', 'value' => 'true'",
    $content
);

$content = str_replace(
    "'key' => 'subscription_status', 'relation' => '=', 'value' => 'free'",
    "'key' => 'subscribed', 'relation' => '=', 'value' => 'false'",
    $content
);

// Also update comments
$content = str_replace(
    "// Target only premium/subscribed users",
    "// Target only premium/subscribed users (Android tag: subscribed=true)",
    $content
);

$content = str_replace(
    "// Target only free users",
    "// Target only free users (Android tag: subscribed=false)",
    $content
);

file_put_contents($path, $content);
echo "Fixed OneSignal tags to match Android app (subscribed=true/false).\n";
