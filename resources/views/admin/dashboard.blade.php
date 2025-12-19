@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-gray-500 text-sm">Total Users</p>
                <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total_users']) }}</p>
                <p class="text-xs text-green-600">+{{ $stats['new_users_today'] }} today</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-gray-500 text-sm">Active Subscriptions</p>
                <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['active_subscriptions']) }}</p>
                <p class="text-xs text-green-600">+{{ $stats['new_subscriptions_today'] }} today</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-gray-500 text-sm">Total Videos</p>
                <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total_videos']) }}</p>
                <p class="text-xs text-gray-500">{{ $stats['total_categories'] }} categories</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-gray-500 text-sm">Total Revenue</p>
                <p class="text-2xl font-bold text-gray-800">₹{{ number_format($stats['total_revenue'], 2) }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Users -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Recent Users</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                @forelse($recent_users as $user)
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-gray-800">{{ $user->name ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-500">{{ $user->phone_number }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">{{ $user->created_at->diffForHumans() }}</p>
                        <span class="inline-block px-2 py-1 text-xs rounded-full {{ $user->is_subscribed ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $user->is_subscribed ? 'Subscribed' : 'Free' }}
                        </span>
                    </div>
                </div>
                @empty
                <p class="text-gray-500 text-center py-4">No users yet</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Recent Payments</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                @forelse($recent_payments as $payment)
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-gray-800">{{ $payment->user->name ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-500">{{ $payment->payment_gateway_id }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-800">₹{{ number_format($payment->amount, 2) }}</p>
                        <span class="inline-block px-2 py-1 text-xs rounded-full {{ $payment->status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ ucfirst($payment->status) }}
                        </span>
                    </div>
                </div>
                @empty
                <p class="text-gray-500 text-center py-4">No payments yet</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Popular Videos -->
<div class="mt-6 bg-white rounded-lg shadow">
    <div class="p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Popular Videos</h3>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">Title</th>
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">Creator</th>
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">Views</th>
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">Interactions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($popular_videos as $video)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4">{{ $video->title }}</td>
                        <td class="py-3 px-4">{{ $video->creator_name }}</td>
                        <td class="py-3 px-4">{{ number_format($video->view_count) }}</td>
                        <td class="py-3 px-4">{{ number_format($video->interactions_count) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-gray-500">No videos yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
