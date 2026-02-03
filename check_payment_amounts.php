<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;

$min = Payment::min('amount');
$max = Payment::max('amount');
$avg = Payment::avg('amount');

echo "min={$min}\n";
echo "max={$max}\n";
echo "avg={$avg}\n";

echo "\nRecent payments:\n";
$recent = Payment::orderByDesc('created_at')->limit(10)->get(['amount','status','created_at','razorpay_payment_id']);
foreach ($recent as $p) {
    echo $p->created_at . " | " . $p->status . " | amount=" . $p->amount . " | " . ($p->razorpay_payment_id ?? '-') . "\n";
}
