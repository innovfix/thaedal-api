<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Register FCM token
     */
    public function registerToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string|max:255',
            'device_type' => 'string|in:android,ios,web',
        ]);

        $user = $request->user();

        $user->fcmTokens()->updateOrCreate(
            ['token' => $request->fcm_token],
            [
                'device_type' => $request->input('device_type', 'android'),
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return $this->success(null, 'FCM token registered');
    }
}

