<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Otp;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        protected SmsService $smsService
    ) {}

    /**
     * Send OTP to phone number
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^[0-9]{10}$/',
            'country_code' => 'string',
        ]);

        $countryCode = $request->input('country_code', '+91');
        $phoneNumber = $countryCode . $request->phone_number;

        // Check rate limiting (max 5 OTPs per hour)
        $recentOtps = Otp::forPhone($phoneNumber)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($recentOtps >= 5) {
            return $this->error('Too many OTP requests. Please try again later.', 429);
        }

        // Generate and save OTP
        $otp = Otp::generate($phoneNumber, $request->ip());

        // Send OTP via SMS
        try {
            $this->smsService->sendOtp($phoneNumber, $otp->otp);
        } catch (\Exception $e) {
            return $this->error('Failed to send OTP. Please try again.', 500);
        }

        return $this->success([
            'message' => 'OTP sent successfully',
            'otp_expires_at' => $otp->expires_at->toIso8601String(),
            'resend_available_at' => now()->addSeconds(30)->toIso8601String(),
        ]);
    }

    /**
     * Verify OTP and login/register user
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
            'otp' => 'required|string|size:6',
            'device_name' => 'required|string|max:100',
            'fcm_token' => 'nullable|string|max:255',
        ]);

        $countryCode = $request->input('country_code', '+91');
        $phoneNumber = $countryCode . $request->phone_number;

        // Verify OTP
        $otp = Otp::verify($phoneNumber, $request->otp);

        if (!$otp) {
            return $this->error('Invalid or expired OTP', 422);
        }

        return DB::transaction(function () use ($request, $phoneNumber) {
            // Find or create user
            $user = User::firstOrCreate(
                ['phone_number' => $phoneNumber],
                [
                    'id' => Str::uuid(),
                    'phone_verified_at' => now(),
                ]
            );

            // Update last login
            $user->update(['last_login_at' => now()]);

            // Create access token
            $token = $user->createToken($request->device_name);

            // Save FCM token if provided
            if ($request->fcm_token) {
                $user->fcmTokens()->updateOrCreate(
                    ['token' => $request->fcm_token],
                    [
                        'device_type' => 'android',
                        'device_name' => $request->device_name,
                        'is_active' => true,
                        'last_used_at' => now(),
                    ]
                );
            }

            return $this->success([
                'user' => new UserResource($user),
                'access_token' => $token->plainTextToken,
                'refresh_token' => Str::random(64), // For future implementation
                'token_type' => 'Bearer',
                'expires_in' => 86400 * 30, // 30 days
            ], 'Login successful');
        });
    }

    /**
     * Refresh access token
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        // For now, just return error - implement proper refresh logic later
        return $this->error('Token refresh not implemented', 501);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Deactivate FCM token if provided
        if ($request->fcm_token) {
            $request->user()->fcmTokens()
                ->where('token', $request->fcm_token)
                ->update(['is_active' => false]);
        }

        return $this->success(null, 'Logged out successfully');
    }
}

