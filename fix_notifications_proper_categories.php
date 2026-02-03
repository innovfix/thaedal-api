<?php
/**
 * Fix notifications to use proper paywall categories:
 * - Cat1_New: New users (free, never subscribed)
 * - Cat2_AutopayOn: Premium users (active subscription)
 * - Cat3_AutopayOffAfter2: Lapsed users (paid Rs 2 but autopay off)
 */

$base = '/var/www/thaedal/api';

// 1) Update OneSignalService with proper category filters
$servicePath = $base . '/app/Services/OneSignalService.php';
@copy($servicePath, $servicePath . '.bak.' . date('Ymd_His'));

$service = <<<'PHP'
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
     *
     * @param string $title
     * @param string $message
     * @param array $options Supported keys: url, audience, big_picture
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
                // Cat1_New: New/Free users who haven't subscribed yet
                $payload['filters'] = [
                    ['field' => 'tag', 'key' => 'paywall_category', 'relation' => '=', 'value' => 'Cat1_New'],
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
                
            case 'free':
                // All free users (not subscribed)
                $payload['filters'] = [
                    ['field' => 'tag', 'key' => 'subscribed', 'relation' => '=', 'value' => 'false'],
                ];
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
PHP;

file_put_contents($servicePath, $service);
echo "Updated OneSignalService with proper category filters.\n";

// 2) Update NotificationController
$controllerPath = $base . '/app/Http/Controllers/Admin/NotificationController.php';
@copy($controllerPath, $controllerPath . '.bak.' . date('Ymd_His'));

$controller = <<<'PHP'
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

    public function index()
    {
        $history = $this->readHistory();

        return view('admin.notifications.index', [
            'configured' => $this->oneSignal->configured(),
            'hasAppId' => $this->oneSignal->hasAppId(),
            'hasRestApiKey' => $this->oneSignal->hasRestApiKey(),
            'appId' => $this->oneSignal->appId(),
            'history' => $history,
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
            'audience' => 'required|in:all,cat1,cat2,cat3,premium,free',
            'big_picture' => 'nullable|url|max:500',
        ]);

        try {
            $resp = $this->oneSignal->send(
                $validated['title'],
                $validated['message'],
                [
                    'url' => $validated['url'] ?? null,
                    'audience' => $validated['audience'],
                    'big_picture' => $validated['big_picture'] ?? null,
                ]
            );

            $audienceLabels = [
                'all' => 'All Users',
                'cat1' => 'Cat1: New Users',
                'cat2' => 'Cat2: Premium (Autopay On)',
                'cat3' => 'Cat3: Lapsed (Autopay Off)',
                'premium' => 'All Subscribed',
                'free' => 'All Free Users',
            ];

            $this->appendHistory([
                'sent_at' => now()->toDateTimeString(),
                'title' => $validated['title'],
                'message' => $validated['message'],
                'url' => $validated['url'] ?? null,
                'audience' => $audienceLabels[$validated['audience']] ?? $validated['audience'],
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
PHP;

file_put_contents($controllerPath, $controller);
echo "Updated NotificationController with proper categories.\n";

// 3) Update create.blade.php with proper category UI
$viewPath = $base . '/resources/views/admin/notifications/create.blade.php';
@copy($viewPath, $viewPath . '.bak.' . date('Ymd_His'));

$createView = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Send Notification')
@section('page_title', 'Send Notification')

@section('content')
@if(!$configured)
    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
        OneSignal is not configured. Please go to <a class="underline" href="{{ route('admin.notifications.settings') }}">Settings</a>.
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Form -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Compose Notification</h2>
        
        <form method="POST" action="{{ route('admin.notifications.send') }}" class="space-y-5" id="notificationForm">
            @csrf

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Title <span class="text-gray-400 font-normal" id="titleCount">(0/80)</span>
                </label>
                <input type="text" name="title" id="titleInput" value="{{ old('title') }}" maxlength="80"
                       placeholder="🎉 New video available!"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- Quick Emojis -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Quick Emojis</label>
                <div class="flex flex-wrap gap-2" id="emojiPicker">
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🎉">🎉</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🔥">🔥</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="⭐">⭐</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🎬">🎬</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="📺">📺</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🎁">🎁</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="💎">💎</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🚀">🚀</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="❤️">❤️</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="✨">✨</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🎯">🎯</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="📢">📢</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🔔">🔔</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="💰">💰</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🏆">🏆</button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Click to insert emoji at cursor position</p>
            </div>

            <!-- Message -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Message <span class="text-gray-400 font-normal" id="messageCount">(0/240)</span>
                </label>
                <textarea name="message" id="messageInput" rows="3" maxlength="240"
                          placeholder="Check out our latest premium content..."
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">{{ old('message') }}</textarea>
                @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- Audience - 3 Categories -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Send To (User Category)</label>
                <div class="grid grid-cols-2 gap-3">
                    <!-- All Users -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="all" class="peer sr-only" {{ old('audience', 'all') === 'all' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-gold peer-checked:bg-gold/10 transition-all">
                            <div class="text-2xl mb-1">👥</div>
                            <div class="text-sm font-medium">All Users</div>
                            <div class="text-xs text-gray-500">Everyone</div>
                        </div>
                    </label>
                    
                    <!-- Cat1: New Users -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="cat1" class="peer sr-only" {{ old('audience') === 'cat1' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all">
                            <div class="text-2xl mb-1">🆕</div>
                            <div class="text-sm font-medium">Cat1: New Users</div>
                            <div class="text-xs text-gray-500">Never subscribed</div>
                        </div>
                    </label>
                    
                    <!-- Cat2: Premium (Autopay On) -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="cat2" class="peer sr-only" {{ old('audience') === 'cat2' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-purple-500 peer-checked:bg-purple-50 transition-all">
                            <div class="text-2xl mb-1">💎</div>
                            <div class="text-sm font-medium">Cat2: Premium</div>
                            <div class="text-xs text-gray-500">Autopay ON</div>
                        </div>
                    </label>
                    
                    <!-- Cat3: Lapsed (Autopay Off) -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="cat3" class="peer sr-only" {{ old('audience') === 'cat3' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-orange-500 peer-checked:bg-orange-50 transition-all">
                            <div class="text-2xl mb-1">⏸️</div>
                            <div class="text-sm font-medium">Cat3: Lapsed</div>
                            <div class="text-xs text-gray-500">Paid ₹2, autopay OFF</div>
                        </div>
                    </label>
                    
                    <!-- All Subscribed -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="premium" class="peer sr-only" {{ old('audience') === 'premium' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all">
                            <div class="text-2xl mb-1">✅</div>
                            <div class="text-sm font-medium">All Subscribed</div>
                            <div class="text-xs text-gray-500">Any active sub</div>
                        </div>
                    </label>
                    
                    <!-- All Free -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="free" class="peer sr-only" {{ old('audience') === 'free' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-gray-500 peer-checked:bg-gray-50 transition-all">
                            <div class="text-2xl mb-1">🆓</div>
                            <div class="text-sm font-medium">All Free Users</div>
                            <div class="text-xs text-gray-500">Not subscribed</div>
                        </div>
                    </label>
                </div>
                @error('audience')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- URL (optional) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Deep Link URL <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="text" name="url" id="urlInput" value="{{ old('url') }}"
                       placeholder="https://thedal.innovfix.ai/..."
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                @error('url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- Image URL (optional) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Image URL <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="url" name="big_picture" id="bigPictureInput" value="{{ old('big_picture') }}"
                       placeholder="https://example.com/image.jpg"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                <p class="text-xs text-gray-500 mt-1">Big picture shown in notification (Android)</p>
                @error('big_picture')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90 disabled:opacity-50" {{ !$configured ? 'disabled' : '' }}>
                    🚀 Send Notification
                </button>
                <a href="{{ route('admin.notifications.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">← Back</a>
            </div>
        </form>
    </div>

    <!-- Preview & Info -->
    <div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">📱 Preview</h2>
            
            <!-- Android Notification Preview -->
            <div class="bg-gray-100 rounded-xl p-4">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <!-- Notification Header -->
                    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100">
                        <div class="w-5 h-5 bg-gradient-to-br from-amber-400 to-amber-600 rounded flex items-center justify-center">
                            <span class="text-white text-xs font-bold">த</span>
                        </div>
                        <span class="text-xs text-gray-600">தேடல் • now</span>
                    </div>
                    
                    <!-- Notification Content -->
                    <div class="px-3 py-2">
                        <div id="previewTitle" class="font-semibold text-gray-900 text-sm">Notification Title</div>
                        <div id="previewMessage" class="text-gray-600 text-sm mt-0.5 line-clamp-2">Your message will appear here...</div>
                    </div>
                    
                    <!-- Big Picture Preview -->
                    <div id="previewImageWrapper" class="hidden px-3 pb-2">
                        <img id="previewImage" src="" alt="Preview" class="w-full h-32 object-cover rounded-lg">
                    </div>
                </div>
            </div>
            
            <!-- Audience Badge -->
            <div class="mt-4 flex items-center gap-2">
                <span class="text-sm text-gray-600">Sending to:</span>
                <span id="previewAudience" class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">All Users</span>
            </div>
        </div>

        <!-- Category Explanation -->
        <div class="bg-white rounded-lg shadow p-6 mt-4">
            <h3 class="font-bold text-gray-800 mb-3">📊 User Categories Explained</h3>
            <div class="space-y-3 text-sm">
                <div class="flex items-start gap-3 p-2 bg-green-50 rounded">
                    <span class="text-xl">🆕</span>
                    <div>
                        <div class="font-semibold text-green-800">Cat1: New Users</div>
                        <div class="text-green-700">Fresh users who haven't paid anything yet. Great for "Try Premium!" promos.</div>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-2 bg-purple-50 rounded">
                    <span class="text-xl">💎</span>
                    <div>
                        <div class="font-semibold text-purple-800">Cat2: Premium (Autopay On)</div>
                        <div class="text-purple-700">Active subscribers with autopay enabled. Send new content alerts!</div>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-2 bg-orange-50 rounded">
                    <span class="text-xl">⏸️</span>
                    <div>
                        <div class="font-semibold text-orange-800">Cat3: Lapsed (Autopay Off)</div>
                        <div class="text-orange-700">Paid ₹2 but turned off autopay. Win them back with "Re-enable autopay" messages!</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tips -->
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-4">
            <h3 class="font-semibold text-amber-800 mb-2">💡 Notification Tips</h3>
            <ul class="text-sm text-amber-700 space-y-1">
                <li>• <b>Cat1:</b> "Start your 7-day free trial today! 🎬"</li>
                <li>• <b>Cat2:</b> "New premium video just dropped! 🔥"</li>
                <li>• <b>Cat3:</b> "We miss you! Re-enable autopay for ₹99/mo 💎"</li>
                <li>• Best times: 9-11 AM, 7-9 PM</li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('titleInput');
    const messageInput = document.getElementById('messageInput');
    const bigPictureInput = document.getElementById('bigPictureInput');
    const titleCount = document.getElementById('titleCount');
    const messageCount = document.getElementById('messageCount');
    const previewTitle = document.getElementById('previewTitle');
    const previewMessage = document.getElementById('previewMessage');
    const previewAudience = document.getElementById('previewAudience');
    const previewImage = document.getElementById('previewImage');
    const previewImageWrapper = document.getElementById('previewImageWrapper');
    const audienceRadios = document.querySelectorAll('input[name="audience"]');
    const emojiButtons = document.querySelectorAll('.emoji-btn');

    // Track last focused input for emoji insertion
    let lastFocusedInput = titleInput;
    
    titleInput.addEventListener('focus', () => lastFocusedInput = titleInput);
    messageInput.addEventListener('focus', () => lastFocusedInput = messageInput);

    // Emoji insertion
    emojiButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const emoji = this.dataset.emoji;
            const input = lastFocusedInput;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const text = input.value;
            input.value = text.substring(0, start) + emoji + text.substring(end);
            input.selectionStart = input.selectionEnd = start + emoji.length;
            input.focus();
            updatePreview();
            updateCounts();
        });
    });

    // Character counters
    function updateCounts() {
        titleCount.textContent = `(${titleInput.value.length}/80)`;
        messageCount.textContent = `(${messageInput.value.length}/240)`;
    }

    // Preview update
    function updatePreview() {
        previewTitle.textContent = titleInput.value || 'Notification Title';
        previewMessage.textContent = messageInput.value || 'Your message will appear here...';
        
        // Image preview
        const imageUrl = bigPictureInput.value.trim();
        if (imageUrl) {
            previewImage.src = imageUrl;
            previewImageWrapper.classList.remove('hidden');
        } else {
            previewImageWrapper.classList.add('hidden');
        }
    }

    // Audience preview
    function updateAudiencePreview() {
        const checked = document.querySelector('input[name="audience"]:checked');
        const labels = {
            all: '👥 All Users',
            cat1: '🆕 Cat1: New Users',
            cat2: '💎 Cat2: Premium',
            cat3: '⏸️ Cat3: Lapsed',
            premium: '✅ All Subscribed',
            free: '🆓 All Free'
        };
        const colors = {
            all: 'bg-blue-100 text-blue-800',
            cat1: 'bg-green-100 text-green-800',
            cat2: 'bg-purple-100 text-purple-800',
            cat3: 'bg-orange-100 text-orange-800',
            premium: 'bg-indigo-100 text-indigo-800',
            free: 'bg-gray-100 text-gray-800'
        };
        previewAudience.textContent = labels[checked.value] || 'All Users';
        previewAudience.className = `px-2 py-1 text-xs font-medium rounded-full ${colors[checked.value] || colors.all}`;
    }

    // Event listeners
    titleInput.addEventListener('input', () => { updateCounts(); updatePreview(); });
    messageInput.addEventListener('input', () => { updateCounts(); updatePreview(); });
    bigPictureInput.addEventListener('input', updatePreview);
    audienceRadios.forEach(radio => radio.addEventListener('change', updateAudiencePreview));

    // Initial
    updateCounts();
    updatePreview();
    updateAudiencePreview();
});
</script>
@endsection
BLADE;

file_put_contents($viewPath, $createView);
echo "Updated create.blade.php with proper 3 categories UI.\n";

// 4) Update index.blade.php to show category colors
$indexPath = $base . '/resources/views/admin/notifications/index.blade.php';
@copy($indexPath, $indexPath . '.bak.' . date('Ymd_His'));

$indexView = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Notifications')
@section('page_title', 'Notifications')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
        @if($configured)
            <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800">✅ Configured</span>
        @else
            <span class="px-3 py-1 text-sm rounded-full bg-red-100 text-red-800">Not configured</span>
            <span class="text-sm text-gray-600">
                @if(!$hasAppId)
                    Missing <b>ONESIGNAL_APP_ID</b>
                @elseif(!$hasRestApiKey)
                    Missing <b>ONESIGNAL_REST_API_KEY</b>
                @endif
            </span>
        @endif
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.notifications.settings') }}" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
            ⚙️ Settings
        </a>
        <a href="{{ route('admin.notifications.create') }}" class="px-4 py-2 gradient-gold text-navy text-sm font-semibold rounded-lg hover:opacity-90">
            📤 Send Notification
        </a>
    </div>
</div>

@if(!$configured)
    <div class="mb-6 p-4 rounded bg-yellow-50 border border-yellow-200 text-sm text-yellow-800">
        OneSignal needs <b>App ID</b> and <b>REST API Key</b>. App ID is {{ $appId ?: 'not set' }}.
        Please open <a class="underline" href="{{ route('admin.notifications.settings') }}">Settings</a> and paste the REST API Key from OneSignal dashboard.
    </div>
@endif

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h2 class="text-lg font-bold text-gray-800">📋 Recent Sent</h2>
        <p class="text-sm text-gray-600">Last 50 notifications sent from the admin panel.</p>
    </div>

    @if(count($history) > 0)
        <div class="divide-y">
            @foreach($history as $entry)
                @php
                    $audienceClass = match(true) {
                        str_contains($entry['audience'] ?? '', 'Cat1') => 'bg-green-100 text-green-800',
                        str_contains($entry['audience'] ?? '', 'Cat2') => 'bg-purple-100 text-purple-800',
                        str_contains($entry['audience'] ?? '', 'Cat3') => 'bg-orange-100 text-orange-800',
                        str_contains($entry['audience'] ?? '', 'Premium') || str_contains($entry['audience'] ?? '', 'Subscribed') => 'bg-indigo-100 text-indigo-800',
                        str_contains($entry['audience'] ?? '', 'Free') => 'bg-gray-100 text-gray-800',
                        default => 'bg-blue-100 text-blue-800',
                    };
                @endphp
                <div class="p-4 hover:bg-gray-50">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-900">{{ $entry['title'] ?? 'Untitled' }}</div>
                            <div class="text-sm text-gray-600 mt-0.5">{{ $entry['message'] ?? '' }}</div>
                            @if(!empty($entry['url']))
                                <div class="text-xs text-blue-600 mt-1 truncate">🔗 {{ $entry['url'] }}</div>
                            @endif
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-xs text-gray-500">{{ $entry['sent_at'] ?? 'Unknown' }}</div>
                            @if(!empty($entry['audience']))
                                <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full {{ $audienceClass }}">
                                    {{ $entry['audience'] }}
                                </span>
                            @endif
                            @if(isset($entry['recipients']))
                                <div class="text-xs text-gray-500 mt-1">📬 {{ number_format($entry['recipients']) }} recipients</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-8 text-center text-gray-500">
            No notifications sent yet.
        </div>
    @endif
</div>
@endsection
BLADE;

file_put_contents($indexPath, $indexView);
echo "Updated index.blade.php with category colors.\n";

echo "\n✅ Notifications updated with proper 3 categories!\n";
echo "\nCategories:\n";
echo "  - Cat1: New Users (never subscribed)\n";
echo "  - Cat2: Premium (autopay ON)\n";
echo "  - Cat3: Lapsed (paid ₹2, autopay OFF)\n";
echo "  - All Subscribed (any active)\n";
echo "  - All Free (not subscribed)\n";
