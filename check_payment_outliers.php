<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;

$count = Payment::where('amount', '>=', 1000)->count();
echo "payments>=1000: {$count}\n";

if ($count > 0) {
    $rows = Payment::where('amount', '>=', 1000)->limit(5)->get(['amount','status','created_at','razorpay_payment_id']);
    foreach ($rows as $p) {
        echo $p->created_at . " | " . $p->status . " | amount=" . $p->amount . " | " . ($p->razorpay_payment_id ?? '-') . "\n";
    }
}
