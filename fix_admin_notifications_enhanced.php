<?php
/**
 * Enhanced Notifications Module:
 * - Emoji picker
 * - 3 audience segments: All / Premium / Free
 * - Character counters
 * - Live preview
 */

$base = '/var/www/thaedal/api';

// Backup helper
function backup_file(string $path): void {
    if (!file_exists($path)) return;
    @copy($path, $path . '.bak.' . date('Ymd_His'));
}

// 1) Enhanced OneSignalService with filters support
$servicePath = $base . '/app/Services/OneSignalService.php';
backup_file($servicePath);

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
     * @param string $title
     * @param string $message
     * @param array $options Supported keys: url, audience (all|premium|free), big_picture
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

        // Audience targeting using OneSignal tags
        // Android app should set tag "subscription_status" = "premium" or "free"
        $audience = $options['audience'] ?? 'all';
        
        if ($audience === 'premium') {
            // Target only premium/subscribed users
            $payload['filters'] = [
                ['field' => 'tag', 'key' => 'subscription_status', 'relation' => '=', 'value' => 'premium'],
            ];
        } elseif ($audience === 'free') {
            // Target only free users
            $payload['filters'] = [
                ['field' => 'tag', 'key' => 'subscription_status', 'relation' => '=', 'value' => 'free'],
            ];
        } else {
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
echo "Updated OneSignalService with audience targeting.\n";

// 2) Enhanced NotificationController
$controllerPath = $base . '/app/Http/Controllers/Admin/NotificationController.php';
backup_file($controllerPath);

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
            'audience' => 'required|in:all,premium,free',
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
                'premium' => 'Premium Users',
                'free' => 'Free Users',
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
echo "Updated NotificationController with audience support.\n";

// 3) Enhanced create.blade.php with emoji picker, audience selector, character counters, preview
$viewPath = $base . '/resources/views/admin/notifications/create.blade.php';
backup_file($viewPath);

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

            <!-- Audience -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Send To</label>
                <div class="grid grid-cols-3 gap-3">
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="all" class="peer sr-only" {{ old('audience', 'all') === 'all' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-gold peer-checked:bg-gold/10 transition-all">
                            <div class="text-2xl mb-1">👥</div>
                            <div class="text-sm font-medium">All Users</div>
                            <div class="text-xs text-gray-500">Everyone</div>
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="premium" class="peer sr-only" {{ old('audience') === 'premium' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-gold peer-checked:bg-gold/10 transition-all">
                            <div class="text-2xl mb-1">💎</div>
                            <div class="text-sm font-medium">Premium</div>
                            <div class="text-xs text-gray-500">Subscribed</div>
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="free" class="peer sr-only" {{ old('audience') === 'free' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-gold peer-checked:bg-gold/10 transition-all">
                            <div class="text-2xl mb-1">🆓</div>
                            <div class="text-sm font-medium">Free Users</div>
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

    <!-- Preview -->
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

        <!-- Tips -->
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-4">
            <h3 class="font-semibold text-amber-800 mb-2">💡 Tips for better notifications</h3>
            <ul class="text-sm text-amber-700 space-y-1">
                <li>• Keep titles short (under 50 chars) for best display</li>
                <li>• Use emojis to grab attention 🎯</li>
                <li>• Personalize for Premium vs Free users</li>
                <li>• Best times: 9-11 AM, 7-9 PM</li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('titleInput');
    const messageInput = document.getElementById('messageInput');
    const urlInput = document.getElementById('urlInput');
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
        const labels = { all: '👥 All Users', premium: '💎 Premium Users', free: '🆓 Free Users' };
        const colors = { all: 'bg-blue-100 text-blue-800', premium: 'bg-purple-100 text-purple-800', free: 'bg-green-100 text-green-800' };
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
echo "Updated create.blade.php with emoji picker, audience selector, preview.\n";

// 4) Update index.blade.php to show audience in history
$indexPath = $base . '/resources/views/admin/notifications/index.blade.php';
backup_file($indexPath);

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
                                <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full 
                                    {{ str_contains($entry['audience'], 'Premium') ? 'bg-purple-100 text-purple-800' : 
                                       (str_contains($entry['audience'], 'Free') ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') }}">
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
echo "Updated index.blade.php with audience display.\n";

echo "\n✅ Enhanced Notifications module deployed!\n";
echo "Features:\n";
echo "  - 15 quick emoji buttons\n";
echo "  - 3 audience options: All / Premium / Free\n";
echo "  - Character counters for title & message\n";
echo "  - Live preview with image support\n";
echo "  - Audience shown in history\n";
