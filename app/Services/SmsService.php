<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $gateway;

    public function __construct()
    {
        $this->gateway = config('services.sms.gateway', 'log');
    }

    /**
     * Send OTP via SMS
     */
    public function sendOtp(string $phoneNumber, string $otp): bool
    {
        $message = "Your Thaedal verification code is: {$otp}. Valid for 5 minutes. Do not share this code.";

        return $this->send($phoneNumber, $message);
    }

    /**
     * Send SMS message
     */
    public function send(string $phoneNumber, string $message): bool
    {
        return match ($this->gateway) {
            'twilio' => $this->sendViaTwilio($phoneNumber, $message),
            'msg91' => $this->sendViaMsg91($phoneNumber, $message),
            'authkey' => $this->sendViaAuthKey($phoneNumber, $message),
            default => $this->logMessage($phoneNumber, $message),
        };
    }

    /**
     * Send via Twilio
     */
    protected function sendViaTwilio(string $phoneNumber, string $message): bool
    {
        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $from = config('services.twilio.phone_number');

            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => $phoneNumber,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                Log::info("SMS sent via Twilio to {$phoneNumber}");
                return true;
            }

            Log::error("Twilio SMS failed: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Twilio SMS error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send via MSG91 (India)
     */
    protected function sendViaMsg91(string $phoneNumber, string $message): bool
    {
        try {
            $authKey = config('services.msg91.auth_key');
            $senderId = config('services.msg91.sender_id');
            $templateId = config('services.msg91.template_id');

            // Extract OTP from message for template
            preg_match('/\d{6}/', $message, $matches);
            $otp = $matches[0] ?? '';

            $response = Http::withHeaders([
                'authkey' => $authKey,
                'Content-Type' => 'application/json',
            ])->post('https://control.msg91.com/api/v5/flow/', [
                'template_id' => $templateId,
                'sender' => $senderId,
                'mobiles' => ltrim($phoneNumber, '+'),
                'otp' => $otp,
            ]);

            if ($response->successful()) {
                Log::info("SMS sent via MSG91 to {$phoneNumber}");
                return true;
            }

            Log::error("MSG91 SMS failed: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("MSG91 SMS error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send via AuthKey (India)
     */
    protected function sendViaAuthKey(string $phoneNumber, string $message): bool
    {
        try {
            $endpoint = config('services.authkey.endpoint', 'https://api.authkey.io/request');
            $apiKey = config('services.authkey.api_key');
            $sid = config('services.authkey.sid');

            preg_match('/\d{6}/', $message, $matches);
            $otp = $matches[0] ?? '';

            $mobile = ltrim($phoneNumber, '+');
            if (str_starts_with($mobile, '91') && strlen($mobile) == 12) {
                $mobile = substr($mobile, 2);
            }

            $params = [
                'authkey' => $apiKey,
                'mobile' => $mobile,
                'country_code' => '91',
                'sid' => $sid,
                'otp' => $otp,
                'company' => 'Thaedal',
            ];

            $response = Http::get($endpoint, $params);

            if ($response->successful()) {
                $body = $response->json();
                Log::info("SMS sent via AuthKey to {$phoneNumber}", ['response' => $body]);
                return true;
            }

            Log::error("AuthKey SMS failed: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("AuthKey SMS error: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Log message (for development)
     */
    protected function logMessage(string $phoneNumber, string $message): bool
    {
        Log::info("SMS to {$phoneNumber}: {$message}");
        return true;
    }
}

