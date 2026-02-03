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
        <form method="GET" class="mt-4 flex flex-wrap items-center gap-2">
            <label class="text-xs text-gray-600">From</label>
            <input type="datetime-local" name="from" value="{{ $fromInput ?? '' }}" class="border rounded px-3 py-2 text-xs">
            <label class="text-xs text-gray-600">To</label>
            <input type="datetime-local" name="to" value="{{ $toInput ?? '' }}" class="border rounded px-3 py-2 text-xs">
            <button type="submit" class="px-3 py-2 text-xs bg-gray-900 text-white rounded">Filter</button>
            @if(!empty($fromInput) || !empty($toInput))
                <a href="{{ route('admin.notifications.index') }}" class="text-xs text-blue-600 hover:underline">Clear</a>
            @endif
        </form>
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
                            @if(!empty($entry['user_id']))
                                <div class="text-xs text-gray-500 mt-1">👤 User ID: {{ $entry['user_id'] }}</div>
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