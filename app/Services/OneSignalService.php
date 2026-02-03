<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OneSignalService
{
    public function appId(): string
    {
        return (string) config('services.onesignal.app_id');
    }

    public function hasAppId(): bool
    {
        return $this->appId() !== '';
    }

    public function hasRestApiKey(): bool
    {
        return (string) config('services.onesignal.rest_api_key') !== '';
    }

    public function configured(): bool
    {
        return $this->hasAppId() && $this->hasRestApiKey();
    }

    /**
     * Send a push notification to OneSignal.
     *
     * Android app sets these tags:
     * - paywall_category: "Cat1_New" | "Cat2_AutopayOn" | "Cat3_AutopayOffAfter2"
     * - subscribed: "true" | "false"
     * - trial: "true" | "false"
     * - external user id: user_id (OneSignal.login(userId))
     *
     * @param string $title
     * @param string $message
     * @param array $options Supported keys: url, audience, big_picture, user_id
     */
    public function send(string $title, string $message, array $options = []): array
    {
        $appId = $this->appId();
        $apiKey = (string) config('services.onesignal.rest_api_key');

        if ($appId === '' || $apiKey === '') {
            throw new \RuntimeException('OneSignal is not configured (missing ONESIGNAL_APP_ID / ONESIGNAL_REST_API_KEY).');
        }

        $payload = [
            'app_id' => $appId,
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
        ];

        // Audience targeting using OneSignal tags set by Android app
        $audience = $options['audience'] ?? 'all';
        
        switch ($audience) {
            case 'cat1':
                // All free users (not subscribed)
                $payload['filters'] = [
                    ['field' => 'tag', 'key' => 'subscribed', 'relation' => '=', 'value' => 'false'],
                ];
                break;
                
            case 'cat2':
                // Cat2_AutopayOn: Premium users with active autopay
                $payload['filters'] = [
                    ['field' => 'tag', 'key' => 'paywall_category', 'relation' => '=', 'value' => 'Cat2_AutopayOn'],
                ];
                break;
                
            case 'cat3':
                // Cat3_AutopayOffAfter2: Users who paid but autopay is off (lapsed)
                $payload['filters'] = [
                    ['field' => 'tag', 'key' => 'paywall_category', 'relation' => '=', 'value' => 'Cat3_AutopayOffAfter2'],
                ];
                break;
                
            case 'premium':
                // All subscribed users (Cat2 autopay on)
                $payload['filters'] = [
                    ['field' => 'tag', 'key' => 'subscribed', 'relation' => '=', 'value' => 'true'],
                ];
                break;
                
            case 'user':
                // Specific user by external user id
                $userId = trim((string) ($options['user_id'] ?? ''));
                if ($userId === '') {
                    throw new \RuntimeException('Missing user_id for specific user notification.');
                }
                $payload['include_external_user_ids'] = [$userId];
                $payload['channel_for_external_user_ids'] = 'push';
                break;
                
            default:
                // All users
                $payload['included_segments'] = ['All'];
        }

        // Optional URL (deep link)
        if (!empty($options['url'])) {
            $payload['url'] = $options['url'];
        }

        // Optional big picture (image)
        if (!empty($options['big_picture'])) {
            $payload['big_picture'] = $options['big_picture'];
        }

        $resp = Http::withHeaders([
            'Authorization' => "Basic {$apiKey}",
            'Accept' => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', $payload);

        if (!$resp->successful()) {
            throw new \RuntimeException('OneSignal API error: ' . $resp->status() . ' ' . $resp->body());
        }

        return $resp->json();
    }

    /**
     * Get notification delivery stats from OneSignal.
     */
    public function getStats(string $notificationId): ?array
    {
        $appId = $this->appId();
        $apiKey = (string) config('services.onesignal.rest_api_key');

        if ($appId === '' || $apiKey === '') {
            return null;
        }

        $resp = Http::withHeaders([
            'Authorization' => "Basic {$apiKey}",
            'Accept' => 'application/json',
        ])->get("https://onesignal.com/api/v1/notifications/{$notificationId}?app_id={$appId}");

        return $resp->successful() ? $resp->json() : null;
    }
}