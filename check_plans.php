<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SubscriptionPlan;

foreach (SubscriptionPlan::all() as $p) {
    echo $p->name . ' price=' . $p->price . ' plan=' . ($p->razorpay_plan_id ?? 'null') . PHP_EOL;
}
