<?php
/**
 * Fix the "Call to a member function copy() on string" error
 * when verification_fee_paid_at is stored as a string instead of datetime
 */

$viewPath = '/var/www/thaedal/api/resources/views/admin/users/index.blade.php';
$content = file_get_contents($viewPath);

// Fix: wrap verification_fee_paid_at in Carbon::parse() to handle string dates
$oldCode = '$user->verification_fee_paid_at->copy()->addDays(7)';
$newCode = '\Carbon\Carbon::parse($user->verification_fee_paid_at)->addDays(7)';

$content = str_replace($oldCode, $newCode, $content);

file_put_contents($viewPath, $content);

echo "✅ Fixed date parsing error in users/index.blade.php\n";

// Verify
if (strpos(file_get_contents($viewPath), $newCode) !== false) {
    echo "✅ Verified: Carbon::parse() is now used\n";
} else {
    echo "❌ Verification failed\n";
}
