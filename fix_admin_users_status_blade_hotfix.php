<?php
/**
 * Hotfix: Update admin users index blade status block to robust Trial/Premium/Free logic.
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$viewPath = '/var/www/thaedal/api/resources/views/admin/users/index.blade.php';
backup_file($viewPath);

$view = file_exists($viewPath) ? file_get_contents($viewPath) : '';
if (!$view) {
    echo "users/index.blade.php not found\n";
    exit(1);
}

$replacement = <<<'BLADE'
                @php
                    $latest = $user->subscriptions->first();

                    // Premium access if admin-forced OR active subscription not expired (or lifetime ends_at NULL)
                    $hasActive = $latest && $latest->status === 'active' && (is_null($latest->ends_at) || optional($latest->ends_at)->gt(now()));
                    $isPremium = (bool) $user->is_subscribed || $hasActive;

                    // Trial access if trial subscription not expired OR "created" subscription in last 7 days (common in trial flows)
                    $trialWindowOk = $latest && (
                        optional($latest->trial_ends_at)->gt(now())
                        || optional($latest->ends_at)->gt(now())
                        || optional($latest->created_at)->gt(now()->subDays(7))
                    );
                    $isTrial = $latest && (
                        (bool) ($latest->is_trial ?? false)
                        || in_array($latest->status, ['trial', 'created'], true)
                    ) && $trialWindowOk;

                    $statusLabel = 'Free';
                    $statusClass = 'bg-gray-100 text-gray-800';

                    if ($isPremium) {
                        $statusLabel = 'Premium Access';
                        $statusClass = 'bg-green-100 text-green-800';
                    } elseif ($isTrial) {
                        $statusLabel = 'Trial Access';
                        $statusClass = 'bg-yellow-100 text-yellow-800';
                    }
                @endphp
BLADE;

// Replace the first @php...@endphp block after the foreach with our replacement (scoped by looking for $latest assignment)
$pattern = '/@php\\s*\\R\\s*\\$latest\\s*=\\s*\\$user->subscriptions->first\\(\\);[\\s\\S]*?@endphp\\R/s';

if (!preg_match($pattern, $view)) {
    echo "Could not locate status block to replace.\n";
    exit(2);
}

$view = preg_replace($pattern, $replacement . "\n", $view, 1);
file_put_contents($viewPath, $view);

echo "Updated status block in: {$viewPath}\n";

