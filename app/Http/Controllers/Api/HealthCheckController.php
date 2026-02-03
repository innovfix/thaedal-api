<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;

class HealthCheckController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $token = $request->header('X-Health-Check-Token');
            $expectedToken = env('HEALTH_CHECK_TOKEN');
            if (!$expectedToken || $token !== $expectedToken) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return $next($request);
        });
    }

    public function health(): JsonResponse
    {
        $checks = ['database' => false, 'cache' => false, 'razorpay' => false];
        try { DB::select('SELECT 1'); $checks['database'] = true; } catch (\Exception $e) {}
        try { Cache::put('hc', 'ok', 10); $checks['cache'] = Cache::get('hc') === 'ok'; } catch (\Exception $e) {}
        try {
            $api = new Api(config('services.razorpay.key_id'), config('services.razorpay.key_secret'));
            $api->order->all(['count' => 1]);
            $checks['razorpay'] = true;
        } catch (\Exception $e) {}
        return response()->json(['status' => in_array(false, $checks) ? 'degraded' : 'healthy', 'checks' => $checks]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['phone_number' => 'required|string']);
        $phone = $request->input('phone_number');
        $cacheKey = "health_otp:{$phone}";
        if ((int)Cache::get($cacheKey, 0) >= 15) {
            return response()->json(['success' => false, 'error' => 'RATE_LIMITED'], 429);
        }
        try {
            $otp = Otp::generate($phone, $request->ip());
            $sent = app(SmsService::class)->sendOtp($phone, $otp->otp);
            if (!$sent) return response()->json(['success' => false, 'error' => 'SMS_FAILED'], 500);
            Cache::put($cacheKey, (int)Cache::get($cacheKey, 0) + 1, now()->addHour());
            return response()->json(['success' => true, 'message' => 'OTP sent']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'INTERNAL_ERROR'], 500);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate(['phone_number' => 'required', 'otp' => 'required|size:6']);
        $otp = Otp::verify($request->input('phone_number'), $request->input('otp'));
        if (!$otp) return response()->json(['success' => false], 400);
        return response()->json(['success' => true, 'token' => 'health_' . uniqid()]);
    }

    public function ping(): JsonResponse
    {
        return response()->json(['pong' => true, 'time' => now()->toIso8601String()]);
    }
}
