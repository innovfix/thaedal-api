<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use App\Services\RazorpayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $selectedDateInput = $request->query('date');
        $selectedDate = $selectedDateInput ? Carbon::parse($selectedDateInput) : now();
        $selectedDate = $selectedDate->startOfDay();
        $selectedDateLabel = $selectedDate->format('M d, Y');
        $selectedDateInput = $selectedDate->toDateString();

        // Separate date for Install/Uninstall card
        $installDateInput = $request->query('install_date');
        $installDate = $installDateInput ? Carbon::parse($installDateInput) : now();
        $installDate = $installDate->startOfDay();
        $installDateInput = $installDate->toDateString();

        $scatterFromInput = $request->query('scatter_from');
        $scatterToInput = $request->query('scatter_to');
        $scatterFrom = $scatterFromInput ? Carbon::parse($scatterFromInput)->startOfDay() : now()->subDay()->startOfDay();
        $scatterTo = $scatterToInput ? Carbon::parse($scatterToInput)->endOfDay() : now()->endOfDay();
        $scatterFromInput = $scatterFrom->toDateString();
        $scatterToInput = $scatterTo->toDateString();

        // Users
        $totalUsers = User::withTrashed()->count();
        $totalFreeUsers = User::withTrashed()
            ->where(function ($q) {
                $q->whereNull('has_paid_verification_fee')
                  ->orWhere('has_paid_verification_fee', false);
            })
            ->count();

        // Active subscriptions
        $validSubQuery = Subscription::query()
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });

        $activeSubscriptionUsers = (int) $validSubQuery->distinct('user_id')->count('user_id');

        // Admin manual premium toggle without a currently-valid subscription record
        $adminForcedPremiumUsers = (int) User::withTrashed()
            ->where('is_subscribed', true)
            ->whereDoesntHave('subscriptions', function ($q) {
                $q->whereIn('status', ['active', 'trial'])
                    ->where(function ($q2) {
                        $q2->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    });
            })
            ->count();

        $premiumUsersTotal = $activeSubscriptionUsers + $adminForcedPremiumUsers;

        // Videos / Categories
        $totalVideos = Video::withTrashed()->count();
        $totalCategories = Category::query()->count();

        $settings = PaymentSetting::current();
        $paywallVideoViews = (int) ($settings->paywall_video_view_count ?? 0);

        // Payments (default to DB totals; override with Razorpay if configured)
        $amountExpr = "CASE WHEN amount >= 1000 OR amount IN (200, 9900, 29900) THEN amount/100 ELSE amount END";
        $totalPaymentDb = (float) Payment::query()
            ->where('status', 'success')
            ->sum(DB::raw($amountExpr));

        $todayPaymentDb = (float) Payment::query()
            ->where('status', 'success')
            ->where(function ($q) use ($selectedDate) {
                $q->whereDate('paid_at', $selectedDate->toDateString())
                    ->orWhere(function ($q2) use ($selectedDate) {
                        $q2->whereNull('paid_at')->whereDate('created_at', $selectedDate->toDateString());
                    });
            })
            ->sum(DB::raw($amountExpr));

        $totalPayment = $totalPaymentDb;
        $todayPayment = $todayPaymentDb;
        $razorpayError = '';

        // Date-based stats
        $todayRegisterUsers = User::query()
            ->whereDate('created_at', $selectedDate->toDateString())
            ->count();

        $todayTrialUsers = User::query()
            ->where('has_paid_verification_fee', true)
            ->whereDate('verification_fee_paid_at', $selectedDate->toDateString())
            ->count();

        $todayPremiumUsers = Subscription::query()
            ->whereIn('status', ['active', 'trial', 'authenticated'])
            ->whereDate('created_at', $selectedDate->toDateString())
            ->distinct('user_id')
            ->count('user_id');

        // Install/Uninstall counts for installDate (separate filter)
        $todayInstalledUsers = User::query()
            ->whereDate('created_at', $installDate->toDateString())
            ->count();

        $todayUninstalledUsers = User::withTrashed()
            ->whereDate('uninstalled_at', $installDate->toDateString())
            ->count();

        // Total installs/uninstalls
        $totalInstalledUsers = User::withTrashed()->count();
        $totalUninstalledUsers = User::withTrashed()->whereNotNull('uninstalled_at')->count();

        // Autopay totals
        $autopayEnabledUsers = Subscription::query()
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->where('users.has_paid_verification_fee', true)
            ->where('auto_renew', true)
            ->whereNotNull('razorpay_subscription_id')
            ->whereNotIn('subscriptions.status', ['cancelled'])
            ->distinct('subscriptions.user_id')
            ->count('subscriptions.user_id');

        $paidUsers = User::withTrashed()
            ->where('has_paid_verification_fee', true)
            ->count();
        $autopayDisabledUsers = max(0, $paidUsers - $autopayEnabledUsers);

        // Upcoming Autopay Forecast - date-wise summary
        $autopaySummary = Subscription::query()
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('subscription_plans', 'subscription_plans.id', '=', 'subscriptions.plan_id')
            ->where('users.has_paid_verification_fee', true)
            ->whereNotNull('subscriptions.razorpay_subscription_id')
            ->whereNotIn('subscriptions.status', ['cancelled'])
            ->whereNotNull('subscriptions.next_billing_date')
            ->where('subscriptions.next_billing_date', '>=', now()->toDateString())
            ->select(
                DB::raw('DATE(subscriptions.next_billing_date) as charge_date'),
                DB::raw('COUNT(DISTINCT users.id) as users_count'),
                DB::raw('COALESCE(SUM(subscription_plans.price), COUNT(DISTINCT users.id) * 299) as expected_amount')
            )
            ->groupBy(DB::raw('DATE(subscriptions.next_billing_date)'))
            ->orderBy('charge_date')
            ->limit(14) // Next 2 weeks
            ->get();

        // Upcoming Autopay - detailed list (filter by autopay_date if provided)
        $autopayDateFilter = $request->query('autopay_date');
        $upcomingAutopayQuery = Subscription::query()
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('subscription_plans', 'subscription_plans.id', '=', 'subscriptions.plan_id')
            ->where('users.has_paid_verification_fee', true)
            ->whereNotNull('subscriptions.razorpay_subscription_id')
            ->whereNotIn('subscriptions.status', ['cancelled'])
            ->whereNotNull('subscriptions.next_billing_date')
            ->select(
                'subscriptions.id',
                'subscriptions.next_billing_date',
                'subscriptions.status',
                'subscriptions.razorpay_subscription_id',
                'users.name as user_name',
                'users.phone_number',
                'users.verification_fee_paid_at',
                DB::raw('COALESCE(subscription_plans.price, 299) as plan_price')
            )
            ->orderBy('subscriptions.next_billing_date');

        if ($autopayDateFilter) {
            $upcomingAutopayQuery->whereDate('subscriptions.next_billing_date', $autopayDateFilter);
        } else {
            $upcomingAutopayQuery->where('subscriptions.next_billing_date', '>=', now()->toDateString());
        }

        $upcomingAutopay = $upcomingAutopayQuery->limit(50)->get();

        $stats = [
            'total_users' => $totalUsers,
            'total_free_users' => $totalFreeUsers,
            'premium_users_total' => $premiumUsersTotal,
            'total_videos' => $totalVideos,
            'total_categories' => $totalCategories,
            'total_payment' => $totalPayment,
            'paywall_video_views' => $paywallVideoViews,
            'today_register_users' => $todayRegisterUsers,
            'today_trial_users' => $todayTrialUsers,
            'today_premium_users' => $todayPremiumUsers,
            'today_installed_users' => $todayInstalledUsers,
            'today_uninstalled_users' => $todayUninstalledUsers,
            'total_installed_users' => $totalInstalledUsers,
            'total_uninstalled_users' => $totalUninstalledUsers,
            'today_payment' => $todayPayment,
            'autopay_enabled_users' => $autopayEnabledUsers,
            'autopay_disabled_users' => $autopayDisabledUsers,
            'razorpay_error' => $razorpayError,
        ];

        $graphType = $request->query('graph', 'today');
        $graphLabels = [];
        $graphValues = [];
        $graphColors = [];
        $graphTitle = '';

        if ($graphType === 'autopay') {
            $graphTitle = 'Autopay Users (Yes / No)';
            $graphLabels = ['Autopay Yes', 'Autopay No'];
            $graphValues = [$autopayEnabledUsers, $autopayDisabledUsers];
            $graphColors = ['#16A34A', '#DC2626'];
        } elseif ($graphType === 'total') {
            $graphTitle = 'Total Users (Registered / Trial / Premium)';
            $graphLabels = ['Total Registered', 'Total Trial', 'Total Premium'];
            $graphValues = [
                $totalUsers,
                User::where('has_paid_verification_fee', true)->count(),
                $premiumUsersTotal,
            ];
            $graphColors = ['#2563EB', '#F59E0B', '#16A34A'];
        } else {
            $graphTitle = 'Today Users (Registered / Trial / Premium)';
            $graphLabels = ['Today Registered', 'Today Trial', 'Today Premium'];
            $graphValues = [$todayRegisterUsers, $todayTrialUsers, $todayPremiumUsers];
            $graphColors = ['#16A34A', '#2563EB', '#F59E0B'];
        }

        $installScatter = User::query()
            ->select('created_at')
            ->whereBetween('created_at', [$scatterFrom, $scatterTo])
            ->orderBy('created_at')
            ->limit(2000)
            ->get()
            ->map(fn ($row) => optional($row->created_at)->toIso8601String())
            ->filter()
            ->values();

        $engagementScatter = collect();
        try {
            $engagementScatter = DB::table('fcm_tokens')
                ->whereNotNull('last_used_at')
                ->whereBetween('last_used_at', [$scatterFrom, $scatterTo])
                ->orderBy('last_used_at')
                ->limit(5000)
                ->pluck('last_used_at')
                ->map(fn ($dt) => Carbon::parse($dt)->toIso8601String())
                ->filter()
                ->values();
        } catch (\Throwable $e) {
            $engagementScatter = collect();
        }

        return view('admin.dashboard', [
            'stats' => $stats,
            'selectedDateInput' => $selectedDateInput,
            'installDateInput' => $installDateInput,
            'selectedDateLabel' => $selectedDateLabel,
            'autopaySummary' => $autopaySummary,
            'upcomingAutopay' => $upcomingAutopay,
            'autopayDateFilter' => $autopayDateFilter,
            'scatterFromInput' => $scatterFromInput,
            'scatterToInput' => $scatterToInput,
            'installScatter' => $installScatter,
            'engagementScatter' => $engagementScatter,
            'graphType' => $graphType,
            'graphLabels' => $graphLabels,
            'graphValues' => $graphValues,
            'graphColors' => $graphColors,
            'graphTitle' => $graphTitle,
        ]);
    }

    // Razorpay totals disabled for dashboard to avoid mismatch with DB.
}
