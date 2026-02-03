<?php
/**
 * Patch script: Add Admin Notifications module (OneSignal push).
 *
 * Adds:
 * - config/services.php onesignal entry
 * - app/Services/OneSignalService.php
 * - app/Http/Controllers/Admin/NotificationController.php
 * - Views:
 *    resources/views/admin/notifications/index.blade.php
 *    resources/views/admin/notifications/create.blade.php
 *    resources/views/admin/notifications/settings.blade.php
 * - Routes in routes/web.php under admin auth
 * - Sidebar link in resources/views/admin/layouts/app.blade.php
 *
 * Uses env/config:
 * - ONESIGNAL_APP_ID
 * - ONESIGNAL_REST_API_KEY
 *
 * Intended to run on the live server where Laravel lives at:
 *   /var/www/thaedal/api
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$base = '/var/www/thaedal/api';
$servicesPath = $base . '/config/services.php';
$oneSignalServicePath = $base . '/app/Services/OneSignalService.php';
$controllerPath = $base . '/app/Http/Controllers/Admin/NotificationController.php';
$routesPath = $base . '/routes/web.php';
$layoutPath = $base . '/resources/views/admin/layouts/app.blade.php';
$viewsDir = $base . '/resources/views/admin/notifications';
$viewIndexPath = $viewsDir . '/index.blade.php';
$viewCreatePath = $viewsDir . '/create.blade.php';
$viewSettingsPath = $viewsDir . '/settings.blade.php';

backup_file($servicesPath);
backup_file($routesPath);
backup_file($layoutPath);
backup_file($controllerPath);
backup_file($oneSignalServicePath);
backup_file($viewIndexPath);
backup_file($viewCreatePath);
backup_file($viewSettingsPath);

// 1) config/services.php -> add onesignal config if missing
$services = file_exists($servicesPath) ? file_get_contents($servicesPath) : '';
if ($services && strpos($services, "'onesignal'") === false) {
    // Insert before closing ];
    $insert = "\n\n    'onesignal' => [\n        'app_id' => env('ONESIGNAL_APP_ID'),\n        'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),\n    ],\n";
    $services = preg_replace('/\n\];\s*$/', $insert . "\n];\n", $services) ?: $services;
    file_put_contents($servicesPath, $services);
}

// 2) OneSignalService
$oneSignalService = <<<'PHP'
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OneSignalService
{
    public function configured(): bool
    {
        return (string) config('services.onesignal.app_id') !== ''
            && (string) config('services.onesignal.rest_api_key') !== '';
    }

    /**
     * Send a push notification to OneSignal.
     *
     * @param string $title
     * @param string $message
     * @param array $options Supported keys: url, included_segments
     */
    public function send(string $title, string $message, array $options = []): array
    {
        $appId = (string) config('services.onesignal.app_id');
        $apiKey = (string) config('services.onesignal.rest_api_key');

        if ($appId === '' || $apiKey === '') {
            throw new \RuntimeException('OneSignal is not configured (missing ONESIGNAL_APP_ID / ONESIGNAL_REST_API_KEY).');
        }

        $payload = [
            'app_id' => $appId,
            'included_segments' => $options['included_segments'] ?? ['All'],
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
        ];

        if (!empty($options['url'])) {
            $payload['url'] = $options['url'];
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
}
PHP;

@mkdir(dirname($oneSignalServicePath), 0775, true);
file_put_contents($oneSignalServicePath, $oneSignalService);

// 3) Admin NotificationController
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
        ]);

        try {
            $resp = $this->oneSignal->send(
                $validated['title'],
                $validated['message'],
                [
                    'url' => $validated['url'] ?? null,
                    'included_segments' => ['All'],
                ]
            );

            $this->appendHistory([
                'sent_at' => now()->toDateTimeString(),
                'title' => $validated['title'],
                'message' => $validated['message'],
                'url' => $validated['url'] ?? null,
                'onesignal_id' => $resp['id'] ?? null,
                'recipients' => $resp['recipients'] ?? null,
            ]);

            return redirect()->route('admin.notifications.index')
                ->with('success', 'Notification sent successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function settings()
    {
        return view('admin.notifications.settings', [
            'appId' => (string) config('services.onesignal.app_id'),
            'configured' => $this->oneSignal->configured(),
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

@mkdir(dirname($controllerPath), 0775, true);
file_put_contents($controllerPath, $controller);

// 4) Views
@mkdir($viewsDir, 0775, true);

$viewIndex = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Notifications')
@section('page_title', 'Notifications')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div class="flex items-center gap-3">
        @if($configured)
            <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800">Configured</span>
        @else
            <span class="px-3 py-1 text-sm rounded-full bg-red-100 text-red-800">Not configured</span>
        @endif
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.notifications.settings') }}" class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
            Settings
        </a>
        <a href="{{ route('admin.notifications.create') }}" class="px-4 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
            Send Notification
        </a>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Recent Sent</h3>
        <p class="text-sm text-gray-500 mt-1">Last 50 notifications sent from the admin panel.</p>
    </div>
    <div class="p-6">
        @if(empty($history))
            <p class="text-gray-500">No notifications sent yet.</p>
        @else
            <div class="space-y-4">
                @foreach($history as $n)
                    <div class="border rounded-lg p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-semibold text-gray-900">{{ $n['title'] ?? '—' }}</div>
                                <div class="text-sm text-gray-700 mt-1">{{ $n['message'] ?? '—' }}</div>
                                @if(!empty($n['url']))
                                    <div class="text-sm mt-2">
                                        <span class="text-gray-500">URL:</span>
                                        <span class="text-gray-700">{{ $n['url'] }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="text-right text-xs text-gray-500">
                                <div>{{ $n['sent_at'] ?? '' }}</div>
                                @if(!empty($n['recipients']))
                                    <div>{{ $n['recipients'] }} recipients</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
BLADE;
file_put_contents($viewIndexPath, $viewIndex);

$viewCreate = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Send Notification')
@section('page_title', 'Send Notification')

@section('content')
@if(!$configured)
    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
        OneSignal is not configured. Please go to <a class="underline" href="{{ route('admin.notifications.settings') }}">Settings</a>.
    </div>
@endif

<div class="bg-white rounded-lg shadow p-6">
    <form method="POST" action="{{ route('admin.notifications.send') }}" class="space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700">Title</label>
            <input type="text" name="title" value="{{ old('title') }}" maxlength="80"
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Message</label>
            <textarea name="message" rows="3" maxlength="240"
                      class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">{{ old('message') }}</textarea>
            @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">URL (optional)</label>
            <input type="text" name="url" value="{{ old('url') }}"
                   placeholder="https://..."
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            @error('url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90" {{ !$configured ? 'disabled' : '' }}>
                Send
            </button>
            <a href="{{ route('admin.notifications.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">Back →</a>
        </div>
    </form>
</div>
@endsection
BLADE;
file_put_contents($viewCreatePath, $viewCreate);

$viewSettings = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Notification Settings')
@section('page_title', 'Notification Settings')

@section('content')
<div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-start justify-between gap-6">
        <div>
            <h2 class="text-xl font-bold text-gray-800">OneSignal Settings</h2>
            <p class="text-gray-600 mt-1">Set OneSignal keys to enable push notifications.</p>
        </div>
        <div>
            @if($configured)
                <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800">Configured</span>
            @else
                <span class="px-3 py-1 text-sm rounded-full bg-red-100 text-red-800">Not configured</span>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.notifications.settings.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700">ONESIGNAL_APP_ID</label>
            <input type="text" name="onesignal_app_id" value="{{ old('onesignal_app_id', $appId) }}"
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            @error('onesignal_app_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">ONESIGNAL_REST_API_KEY</label>
            <input type="password" name="onesignal_rest_api_key" value=""
                   placeholder="Enter to update (leave blank to keep existing)"
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            @error('onesignal_rest_api_key')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
                Save
            </button>
            <a href="{{ route('admin.notifications.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">Back →</a>
        </div>
    </form>
</div>
@endsection
BLADE;
file_put_contents($viewSettingsPath, $viewSettings);

// 5) routes/web.php: import + routes + sidebar
$routes = file_exists($routesPath) ? file_get_contents($routesPath) : '';
if ($routes) {
    if (strpos($routes, 'NotificationController') === false) {
        $routes = str_replace(
            "use App\\Http\\Controllers\\Admin\\PaymentController;\n",
            "use App\\Http\\Controllers\\Admin\\PaymentController;\nuse App\\Http\\Controllers\\Admin\\NotificationController;\n",
            $routes
        );
    }

    if (strpos($routes, "admin.notifications.index") === false && strpos($routes, "notifications', [NotificationController") === false) {
        // Insert after payments resource route
        $marker = "        // Payment Management\n        Route::resource('payments', PaymentController::class)->only(['index', 'show']);";
        $insert = $marker . "\n\n        // Notifications (OneSignal)\n        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');\n        Route::get('notifications/new', [NotificationController::class, 'create'])->name('notifications.create');\n        Route::post('notifications', [NotificationController::class, 'send'])->name('notifications.send');\n        Route::get('notifications/settings', [NotificationController::class, 'settings'])->name('notifications.settings');\n        Route::put('notifications/settings', [NotificationController::class, 'updateSettings'])->name('notifications.settings.update');";
        $routes = str_replace($marker, $insert, $routes);
    }

    file_put_contents($routesPath, $routes);
}

// 6) Sidebar link
$layout = file_exists($layoutPath) ? file_get_contents($layoutPath) : '';
if ($layout && strpos($layout, "admin.notifications") === false) {
    // Insert after Payments link block
    $needle = "                    Payments\n                </a>\n";
    if (strpos($layout, $needle) !== false) {
        $notifLink = <<<'HTML'

                <a href="{{ route('admin.notifications.index') }}" 
                   class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-gold {{ request()->routeIs('admin.notifications.*') ? 'bg-gray-800 text-gold border-l-4 border-gold' : '' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0h6z"/>
                    </svg>
                    Notifications
                </a>
HTML;
        $layout = str_replace($needle, $needle . $notifLink, $layout);
        file_put_contents($layoutPath, $layout);
    }
}

echo "Patched Notifications module.\n";
echo "- {$servicesPath}\n- {$oneSignalServicePath}\n- {$controllerPath}\n- {$routesPath}\n- {$layoutPath}\n- {$viewIndexPath}\n- {$viewCreatePath}\n- {$viewSettingsPath}\n";

