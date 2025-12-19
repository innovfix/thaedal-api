<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FcmService
{
    protected $messaging = null;

    public function __construct()
    {
        // Firebase SDK not installed - using log mode
    }

    /**
     * Send notification to a single user
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        $tokens = $user->fcmTokens()->active()->pluck('token')->toArray();

        if (empty($tokens)) {
            Log::info("No FCM tokens found for user {$user->id}");
            return false;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send notification to multiple tokens
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): bool
    {
        if (!$this->messaging) {
            Log::warning("FCM not configured, logging notification instead");
            Log::info("FCM Notification: {$title} - {$body}", ['tokens' => $tokens, 'data' => $data]);
            return true;
        }

        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            $report = $this->messaging->sendMulticast($message, $tokens);

            // Handle failed tokens
            if ($report->hasFailures()) {
                foreach ($report->failures()->getItems() as $failure) {
                    $token = $failure->target()->value();
                    $error = $failure->error()->getMessage();
                    
                    Log::warning("FCM send failed for token: {$token}, error: {$error}");
                    
                    // Deactivate invalid tokens
                    if (str_contains($error, 'not-registered') || str_contains($error, 'invalid-registration-token')) {
                        FcmToken::where('token', $token)->update(['is_active' => false]);
                    }
                }
            }

            Log::info("FCM sent to {$report->successes()->count()} devices, {$report->failures()->count()} failed");
            
            return $report->successes()->count() > 0;
        } catch (\Exception $e) {
            Log::error("FCM send error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to a topic
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        if (!$this->messaging) {
            Log::warning("FCM not configured");
            return false;
        }

        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);
            
            Log::info("FCM sent to topic: {$topic}");
            return true;
        } catch (\Exception $e) {
            Log::error("FCM topic send error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send new video notification to all users
     */
    public function notifyNewVideo(string $videoId, string $title, string $thumbnail): bool
    {
        return $this->sendToTopic('new_videos', 'New Video', $title, [
            'type' => 'new_video',
            'video_id' => $videoId,
            'title' => $title,
            'thumbnail' => $thumbnail,
        ]);
    }

    /**
     * Send subscription reminder
     */
    public function sendSubscriptionReminder(User $user, int $daysRemaining): bool
    {
        $title = 'Subscription Reminder';
        $body = $daysRemaining === 1 
            ? 'Your subscription expires tomorrow!' 
            : "Your subscription expires in {$daysRemaining} days.";

        return $this->sendToUser($user, $title, $body, [
            'type' => 'subscription_reminder',
            'days_remaining' => (string) $daysRemaining,
        ]);
    }

    /**
     * Send payment success notification
     */
    public function sendPaymentSuccess(User $user, string $amount, string $planName): bool
    {
        return $this->sendToUser($user, 'Payment Successful', "Your payment of {$amount} for {$planName} was successful.", [
            'type' => 'payment_success',
            'amount' => $amount,
            'plan_name' => $planName,
        ]);
    }
}

