<?php
/**
 * Fetch all payments from Razorpay API and show who paid ₹2
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RazorpayService;
use App\Models\User;
use App\Models\Payment;

$razorpay = app(RazorpayService::class);

echo "=== FETCHING PAYMENTS FROM RAZORPAY API ===\n\n";

try {
    // Fetch payments from last 90 days
    $fromTs = now()->subDays(90)->timestamp;
    $toTs = now()->timestamp;
    
    $allPayments = [];
    $skip = 0;
    $count = 100;
    
    while (true) {
        $items = $razorpay->listPayments($fromTs, $toTs, $count, $skip);
        if (empty($items)) break;
        
        foreach ($items as $p) {
            $allPayments[] = $p;
        }
        
        if (count($items) < $count) break;
        $skip += $count;
    }
    
    echo "Total payments from Razorpay: " . count($allPayments) . "\n\n";
    
    echo "=== ALL CAPTURED/AUTHORIZED PAYMENTS ===\n";
    echo str_pad("Amount", 10) . " | " . str_pad("Status", 12) . " | " . str_pad("Contact", 15) . " | " . str_pad("Email", 25) . " | Payment ID\n";
    echo str_repeat("-", 100) . "\n";
    
    $twoRsPayments = [];
    
    foreach ($allPayments as $p) {
        $status = $p['status'] ?? 'unknown';
        $amount = ($p['amount'] ?? 0) / 100; // paise to rupees
        $contact = $p['contact'] ?? 'N/A';
        $email = $p['email'] ?? 'N/A';
        $paymentId = $p['id'] ?? 'N/A';
        $method = $p['method'] ?? 'N/A';
        $createdAt = isset($p['created_at']) ? date('Y-m-d H:i', $p['created_at']) : 'N/A';
        
        // Only show captured payments
        if ($status === 'captured') {
            echo str_pad("₹" . number_format($amount, 2), 10) . " | " 
                . str_pad($status, 12) . " | " 
                . str_pad($contact, 15) . " | " 
                . str_pad(substr($email, 0, 25), 25) . " | " 
                . $paymentId . "\n";
            
            // Collect ₹2 payments
            if ($amount >= 1.5 && $amount <= 2.5) {
                $twoRsPayments[] = [
                    'amount' => $amount,
                    'contact' => $contact,
                    'email' => $email,
                    'payment_id' => $paymentId,
                    'method' => $method,
                    'created_at' => $createdAt,
                ];
            }
        }
    }
    
    echo "\n\n=== ₹2 VERIFICATION FEE PAYMENTS ===\n";
    if (empty($twoRsPayments)) {
        echo "No ₹2 payments found in Razorpay!\n";
    } else {
        echo "Found " . count($twoRsPayments) . " payment(s) of ₹2:\n\n";
        
        foreach ($twoRsPayments as $i => $p) {
            echo ($i + 1) . ". Phone: {$p['contact']}\n";
            echo "   Email: {$p['email']}\n";
            echo "   Amount: ₹{$p['amount']}\n";
            echo "   Method: {$p['method']}\n";
            echo "   Payment ID: {$p['payment_id']}\n";
            echo "   Date: {$p['created_at']}\n";
            
            // Check if this user exists in our database
            $phone = $p['contact'];
            $user = User::where('phone_number', $phone)
                ->orWhere('phone_number', '+91' . ltrim($phone, '+91'))
                ->orWhere('phone_number', ltrim($phone, '+'))
                ->first();
            
            if ($user) {
                echo "   DB User: {$user->name} ({$user->phone_number})\n";
                echo "   has_paid_verification_fee in DB: " . ($user->has_paid_verification_fee ? 'YES ✅' : 'NO ❌') . "\n";
            } else {
                echo "   DB User: NOT FOUND ❌\n";
            }
            
            // Check if payment exists in our payments table
            $dbPayment = Payment::where('razorpay_payment_id', $p['payment_id'])->first();
            echo "   Payment in DB: " . ($dbPayment ? 'YES ✅' : 'NO ❌') . "\n";
            
            echo "\n";
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Total captured payments in Razorpay: " . count(array_filter($allPayments, fn($p) => ($p['status'] ?? '') === 'captured')) . "\n";
    echo "₹2 payments: " . count($twoRsPayments) . "\n";
    echo "Payments in our DB: " . Payment::count() . "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
