<?php
/**
 * Patch script: Improve Notifications "Not configured" UX.
 * - Show partial configuration (App ID set vs REST key set)
 * - Provide clear instructions in settings page
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$servicePath = '/var/www/thaedal/api/app/Services/OneSignalService.php';
$indexViewPath = '/var/www/thaedal/api/resources/views/admin/notifications/index.blade.php';
$settingsViewPath = '/var/www/thaedal/api/resources/views/admin/notifications/settings.blade.php';
$controllerPath = '/var/www/thaedal/api/app/Http/Controllers/Admin/NotificationController.php';

backup_file($servicePath);
backup_file($indexViewPath);
backup_file($settingsViewPath);
backup_file($controllerPath);

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
     * @param array $options Supported keys: url, included_segments
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
file_put_contents($servicePath, $service);

// Controller: pass detailed config flags to views
$controller = file_exists($controllerPath) ? file_get_contents($controllerPath) : '';
if ($controller) {
    // Replace index() and settings() return arrays to include flags (simple string replace best-effort)
    $controller = preg_replace(
        "/return view\\('admin\\.notifications\\.index', \\[([\\s\\S]*?)\\]\\);/m",
        "return view('admin.notifications.index', [\n            'configured' => \$this->oneSignal->configured(),\n            'hasAppId' => \$this->oneSignal->hasAppId(),\n            'hasRestApiKey' => \$this->oneSignal->hasRestApiKey(),\n            'appId' => \$this->oneSignal->appId(),\n            'history' => \$history,\n        ]);",
        $controller,
        1
    ) ?: $controller;

    $controller = preg_replace(
        "/return view\\('admin\\.notifications\\.settings', \\[([\\s\\S]*?)\\]\\);/m",
        "return view('admin.notifications.settings', [\n            'appId' => (string) config('services.onesignal.app_id'),\n            'configured' => \$this->oneSignal->configured(),\n            'hasAppId' => \$this->oneSignal->hasAppId(),\n            'hasRestApiKey' => \$this->oneSignal->hasRestApiKey(),\n        ]);",
        $controller,
        1
    ) ?: $controller;

    file_put_contents($controllerPath, $controller);
}

// Index view: show partial status + guide
$index = <<<'BLADE'
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
        <a href="{{ route('admin.notifications.settings') }}" class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
            Settings
        </a>
        <a href="{{ route('admin.notifications.create') }}" class="px-4 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
            Send Notification
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
file_put_contents($indexViewPath, $index);

// Settings view: add clear help text
$settings = <<<'BLADE'
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

    <div class="mt-4 p-4 rounded bg-gray-50 text-sm text-gray-700">
        <div class="font-semibold mb-2">Where to find these in OneSignal</div>
        <ul class="list-disc pl-5 space-y-1">
            <li><b>App ID</b>: OneSignal → Your App → Settings → Keys & IDs</li>
            <li><b>REST API Key</b>: Same page (keep it secret)</li>
        </ul>
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
                   placeholder="Paste here to update (leave blank to keep existing)"
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            @error('onesignal_rest_api_key')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @if(!$hasRestApiKey)
                <p class="text-xs text-red-600 mt-1">REST API Key is missing. Notifications cannot be sent until it is set.</p>
            @endif
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
file_put_contents($settingsViewPath, $settings);

echo "Patched OneSignal config status UX.\n";

