<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;

echo "=== ALL PAYMENTS (Last 20) ===\n\n";
echo str_pad("Phone", 16) . " | " . str_pad("Status", 10) . " | " . str_pad("Amount", 10) . " | Date\n";
echo str_repeat("-", 70) . "\n";

$payments = Payment::with('user')->latest()->limit(20)->get();

foreach ($payments as $p) {
    $phone = $p->user->phone_number ?? 'N/A';
    $status = $p->status ?? 'unknown';
    $amount = "₹" . number_format($p->amount, 2);
    $date = $p->paid_at ?? $p->created_at;
    
    echo str_pad($phone, 16) . " | " . str_pad($status, 10) . " | " . str_pad($amount, 10) . " | " . $date . "\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total payments: " . Payment::count() . "\n";
echo "Success: " . Payment::where('status', 'success')->count() . "\n";
echo "Pending: " . Payment::where('status', 'pending')->count() . "\n";
echo "Failed: " . Payment::where('status', 'failed')->count() . "\n";
