<?php
/**
 * Diagnostic script to check dashboard data on the server.
 * Run: php check_dashboard_data.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Subscription;
use App\Models\Payment;
use App\Models\User;

echo "=== DASHBOARD DATA CHECK ===\n\n";

// Subscriptions
echo "--- SUBSCRIPTIONS ---\n";
$allSubs = Subscription::count();
$activeSubs = Subscription::whereIn('status', ['active', 'trial'])->count();
$validSubs = Subscription::whereIn('status', ['active', 'trial'])
    ->where(function($q) {
        $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
    })->count();

echo "Total subscriptions: {$allSubs}\n";
echo "Active/Trial status: {$activeSubs}\n";
echo "Valid (not expired): {$validSubs}\n";

// Group by status
echo "\nBy status:\n";
$byStatus = Subscription::selectRaw('status, count(*) as cnt')->groupBy('status')->get();
foreach ($byStatus as $row) {
    echo "  {$row->status}: {$row->cnt}\n";
}

// Users
echo "\n--- USERS ---\n";
$totalUsers = User::count();
$subscribedUsers = User::where('is_subscribed', true)->count();
echo "Total users: {$totalUsers}\n";
echo "is_subscribed=true: {$subscribedUsers}\n";

// Payments
echo "\n--- PAYMENTS ---\n";
$totalPayments = Payment::count();
$successPayments = Payment::where('status', 'success')->count();
echo "Total payments: {$totalPayments}\n";
echo "Successful payments: {$successPayments}\n";

echo "\nRecent 10 payments:\n";
$recent = Payment::with('user:id,name,phone_number')
    ->orderByDesc('created_at')
    ->limit(10)
    ->get(['id', 'user_id', 'amount', 'status', 'created_at', 'paid_at']);

foreach ($recent as $p) {
    $userName = $p->user->name ?? $p->user->phone_number ?? 'N/A';
    echo "  ₹{$p->amount} | {$p->status} | {$userName} | {$p->created_at}\n";
}

echo "\n=== END ===\n";
