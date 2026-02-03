<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OneSignalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class NotificationController extends Controller
{
    private string $historyPath;

    public function __construct(private readonly OneSignalService $oneSignal)
    {
        $this->historyPath = storage_path('app/admin_notification_history.json');
    }

    public function index(Request $request)
    {
        $history = $this->readHistory();

        $fromInput = $request->query('from');
        $toInput = $request->query('to');
        $from = null;
        $to = null;
        try {
            if ($fromInput) {
                $from = \Carbon\Carbon::parse($fromInput);
            }
            if ($toInput) {
                $to = \Carbon\Carbon::parse($toInput);
            }
        } catch (\Throwable $e) {
            $from = null;
            $to = null;
        }

        if ($from || $to) {
            $history = array_values(array_filter($history, function ($entry) use ($from, $to) {
                if (empty($entry['sent_at'])) {
                    return false;
                }
                try {
                    $sentAt = \Carbon\Carbon::parse($entry['sent_at']);
                } catch (\Throwable $e) {
                    return false;
                }
                if ($from && $sentAt->lt($from)) {
                    return false;
                }
                if ($to && $sentAt->gt($to)) {
                    return false;
                }
                return true;
            }));
        }

        return view('admin.notifications.index', [
            'configured' => $this->oneSignal->configured(),
            'hasAppId' => $this->oneSignal->hasAppId(),
            'hasRestApiKey' => $this->oneSignal->hasRestApiKey(),
            'appId' => $this->oneSignal->appId(),
            'history' => $history,
            'fromInput' => $fromInput,
            'toInput' => $toInput,
        ]);
    }

    public function create()
    {
        return view('admin.notifications.create', [
            'configured' => $this->oneSignal->configured(),
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:80',
            'message' => 'required|string|max:240',
            'url' => 'nullable|string|max:500',
            'audience' => 'required|in:all,cat1,cat2,cat3,premium,user',
            'big_picture' => 'nullable|url|max:500',
            'user_id' => 'required_if:audience,user|nullable|string|max:36|exists:users,id',
        ]);

        try {
            $resp = $this->oneSignal->send(
                $validated['title'],
                $validated['message'],
                [
                    'url' => $validated['url'] ?? null,
                    'audience' => $validated['audience'],
                    'big_picture' => $validated['big_picture'] ?? null,
                    'user_id' => $validated['user_id'] ?? null,
                ]
            );

            $audienceLabels = [
                'all' => 'All Users',
                'cat1' => 'Cat1: All Free Users',
                'cat2' => 'Cat2: Premium (Autopay On)',
                'cat3' => 'Cat3: Lapsed (Autopay Off)',
                'premium' => 'All Subscribed',
                'user' => 'Specific User',
            ];

            $this->appendHistory([
                'sent_at' => now()->toDateTimeString(),
                'title' => $validated['title'],
                'message' => $validated['message'],
                'url' => $validated['url'] ?? null,
                'audience' => $audienceLabels[$validated['audience']] ?? $validated['audience'],
                'user_id' => $validated['user_id'] ?? null,
                'onesignal_id' => $resp['id'] ?? null,
                'recipients' => $resp['recipients'] ?? null,
            ]);

            $recipientCount = $resp['recipients'] ?? 0;
            return redirect()->route('admin.notifications.index')
                ->with('success', "Notification sent successfully to {$recipientCount} recipients.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function settings()
    {
        return view('admin.notifications.settings', [
            'appId' => (string) config('services.onesignal.app_id'),
            'configured' => $this->oneSignal->configured(),
            'hasAppId' => $this->oneSignal->hasAppId(),
            'hasRestApiKey' => $this->oneSignal->hasRestApiKey(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'onesignal_app_id' => 'nullable|string|max:128',
            'onesignal_rest_api_key' => 'nullable|string|max:256',
        ]);

        $envPath = base_path('.env');
        if (!File::exists($envPath) || !File::isWritable($envPath)) {
            return back()->with('error', '.env not writable on server. Please update ONESIGNAL_APP_ID / ONESIGNAL_REST_API_KEY manually.');
        }

        $appId = trim((string) ($validated['onesignal_app_id'] ?? ''));
        $apiKey = trim((string) ($validated['onesignal_rest_api_key'] ?? ''));

        if ($appId !== '') {
            $this->envSet($envPath, 'ONESIGNAL_APP_ID', $appId);
        }
        if ($apiKey !== '') {
            $this->envSet($envPath, 'ONESIGNAL_REST_API_KEY', $apiKey);
        }

        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            // ignore
        }

        return back()->with('success', 'Notification settings updated.');
    }

    private function envSet(string $envPath, string $key, string $value): void
    {
        $value = str_replace(["\r", "\n"], '', $value);
        $needsQuotes = (bool) preg_match('~[\s#="]~', $value);
        $escaped = $needsQuotes ? '"' . addcslashes($value, '"') . '"' : $value;

        $lines = File::exists($envPath)
            ? preg_split("~\r\n|\n|\r~", (string) File::get($envPath))
            : [];

        $found = false;
        foreach ($lines as $i => $line) {
            if (preg_match('~^\s*' . preg_quote($key, '~') . '\s*=~u', (string) $line)) {
                $lines[$i] = $key . '=' . $escaped;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $lines[] = $key . '=' . $escaped;
        }

        File::put($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    private function readHistory(): array
    {
        if (!File::exists($this->historyPath)) {
            return [];
        }
        $raw = File::get($this->historyPath);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function appendHistory(array $entry): void
    {
        $history = $this->readHistory();
        array_unshift($history, $entry);
        $history = array_slice($history, 0, 50);
        File::ensureDirectoryExists(dirname($this->historyPath));
        File::put($this->historyPath, json_encode($history, JSON_PRETTY_PRINT));
    }
}