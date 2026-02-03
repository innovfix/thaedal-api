<?php
/**
 * Patch script: Enable Razorpay Gross (Captured) and Net (Settled) cards on Admin Dashboard.
 *
 * Uses App\Services\RazorpayService (requires RAZORPAY_KEY_ID/RAZORPAY_KEY_SECRET in .env).
 * Fetches within selected range using Razorpay API listPayments/listSettlements with pagination caps.
 *
 * Intended to run on the live server where Laravel lives at:
 *   /var/www/thaedal/api
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$path = '/var/www/thaedal/api/app/Http/Controllers/Admin/DashboardController.php';
backup_file($path);

$content = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use App\Services\RazorpayService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Range selector (used for revenue + new counts). Active subscriptions should be "current", not range-limited.
        $range = (string) $request->query('range', '30d');

        [$from, $rangeLabel] = match ($range) {
            'today' => [now()->startOfDay(), 'Today'],
            '7d' => [now()->subDays(7)->startOfDay(), 'Last 7 days'],
            '30d' => [now()->subDays(30)->startOfDay(), 'Last 30 days'],
            'all' => [null, 'All time'],
            default => [now()->subDays(30)->startOfDay(), 'Last 30 days'],
        };

        // Users
        $totalUsers = User::query()->count();
        $newUsersToday = User::query()->whereDate('created_at', now()->toDateString())->count();

        // Active subscriptions
        $validSubQuery = Subscription::query()
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });

        $activeSubscriptionUsers = (int) $validSubQuery->distinct('user_id')->count('user_id');

        // Admin manual premium toggle without a currently-valid subscription record
        $adminForcedPremiumUsers = (int) User::query()
            ->where('is_subscribed', true)
            ->whereDoesntHave('subscriptions', function ($q) {
                $q->whereIn('status', ['active', 'trial'])
                    ->where(function ($q2) {
                        $q2->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    });
            })
            ->count();

        $activeSubscriptions = $activeSubscriptionUsers + $adminForcedPremiumUsers;

        // New subscriptions today (counts subscription records created today)
        $newSubscriptionsToday = Subscription::query()
            ->whereIn('status', ['active', 'trial'])
            ->whereDate('created_at', now()->toDateString())
            ->count();

        // Videos / Categories
        $totalVideos = Video::query()->count();
        $totalCategories = Category::query()->count();

        // Payments / revenue (use paid_at when available)
        $paymentsForRevenue = Payment::query()->where('status', 'success');
        if ($from instanceof Carbon) {
            $paymentsForRevenue->where(function ($q) use ($from) {
                $q->where('paid_at', '>=', $from)
                    ->orWhere(function ($q2) use ($from) {
                        $q2->whereNull('paid_at')->where('created_at', '>=', $from);
                    });
            });
        }
        $dbRevenue = (float) $paymentsForRevenue->sum('amount');

        // Recent lists
        $recentUsers = User::query()->latest('created_at')->limit(5)->get();

        $recentPayments = Payment::query()
            ->with('user')
            ->orderByRaw('CASE WHEN paid_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $popularVideos = Video::query()->latest('created_at')->limit(10)->get();

        // Razorpay stats
        $razorpayGross = null;
        $razorpaySettled = null;
        $razorpayError = '';

        // Pick time window for Razorpay API (needs from/to timestamps)
        $toTs = now()->timestamp;
        $fromTs = $from instanceof Carbon ? $from->timestamp : now()->subDays(365)->startOfDay()->timestamp; // cap "all" at 365 days

        try {
            /** @var RazorpayService $razorpay */
            $razorpay = app(RazorpayService::class);

            // Gross: sum captured payments
            $grossPaise = 0;
            $skip = 0;
            $page = 0;
            $count = 100;
            $maxPages = 10; // cap to 1000 records to avoid slow dashboard
            while ($page < $maxPages) {
                $items = $razorpay->listPayments($fromTs, $toTs, $count, $skip);
                if (empty($items)) break;

                foreach ($items as $p) {
                    $status = (string) ($p['status'] ?? '');
                    if ($status === 'captured') {
                        $grossPaise += (int) ($p['amount'] ?? 0);
                    }
                }
                if (count($items) < $count) break;
                $skip += $count;
                $page++;
            }
            $razorpayGross = $grossPaise / 100.0;

            // Net: sum settlements
            $settledPaise = 0;
            $skip = 0;
            $page = 0;
            while ($page < $maxPages) {
                $items = $razorpay->listSettlements($fromTs, $toTs, $count, $skip);
                if (empty($items)) break;
                foreach ($items as $s) {
                    $settledPaise += (int) ($s['amount'] ?? 0);
                }
                if (count($items) < $count) break;
                $skip += $count;
                $page++;
            }
            $razorpaySettled = $settledPaise / 100.0;
        } catch (\Throwable $e) {
            // Keep graceful rendering
            $razorpayError = $e->getMessage();
        }

        $stats = [
            'total_users' => $totalUsers,
            'new_users_today' => $newUsersToday,
            'active_subscriptions' => $activeSubscriptions,
            'new_subscriptions_today' => $newSubscriptionsToday,
            'total_videos' => $totalVideos,
            'total_categories' => $totalCategories,
            'db_revenue' => $dbRevenue,
            'razorpay_gross' => $razorpayGross,
            'razorpay_settled' => $razorpaySettled,
            'razorpay_error' => $razorpayError,
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'recent_users' => $recentUsers,
            'recent_payments' => $recentPayments,
            'popular_videos' => $popularVideos,
            'range' => $range,
            'rangeLabel' => $rangeLabel,
        ]);
    }
}
PHP;

@mkdir(dirname($path), 0775, true);
file_put_contents($path, $content);

echo "DashboardController updated with Razorpay cards: {$path}\n";

