<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Video;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'total_videos' => Video::count(),
            'total_categories' => Category::count(),
            'total_revenue' => Payment::where('status', 'success')->sum('amount'),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_subscriptions_today' => Subscription::whereDate('created_at', today())->count(),
        ];

        $recent_users = User::latest()->take(5)->get();
        $recent_payments = Payment::with('user')->latest()->take(10)->get();
        $popular_videos = Video::withCount('interactions')
            ->orderBy('interactions_count', 'desc')
            ->take(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recent_users', 'recent_payments', 'popular_videos'));
    }

    /**
     * Get install/uninstall stats for a specific date
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function installStats(Request $request)
    {
        $date = $request->input('date', today()->toDateString());
        
        try {
            $targetDate = Carbon::parse($date);
        } catch (\Exception $e) {
            $targetDate = today();
        }

        $installedCount = User::whereDate('created_at', $targetDate)->count();
        $uninstalledCount = User::whereDate('uninstalled_at', $targetDate)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $targetDate->toDateString(),
                'installed_count' => $installedCount,
                'uninstalled_count' => $uninstalledCount,
            ]
        ]);
    }
}
