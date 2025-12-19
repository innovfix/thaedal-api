@extends('admin.layouts.app')

@section('title', 'User Details')
@section('page_title', 'User Details')

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.users.index') }}" class="text-blue-600 hover:text-blue-900">&larr; Back to Users</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- User Info Card -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">User Information</h3>
            <div class="space-y-3">
                <div>
                    <p class="text-sm text-gray-500">Name</p>
                    <p class="font-medium">{{ $user->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Phone Number</p>
                    <p class="font-medium">{{ $user->phone_number }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="font-medium">{{ $user->email ?? 'Not provided' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $user->is_subscribed ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ $user->is_subscribed ? 'Premium' : 'Free' }}
                    </span>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Joined</p>
                    <p class="font-medium">{{ $user->created_at->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Last Active</p>
                    <p class="font-medium">{{ $user->updated_at->diffForHumans() }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Cards -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Subscriptions -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Subscriptions</h3>
            </div>
            <div class="p-6">
                @forelse($user->subscriptions as $subscription)
                <div class="mb-4 p-4 border rounded">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium">{{ $subscription->plan->name ?? 'N/A' }}</p>
                            <p class="text-sm text-gray-500">Status: 
                                <span class="font-medium {{ $subscription->status === 'active' ? 'text-green-600' : 'text-gray-600' }}">
                                    {{ ucfirst($subscription->status) }}
                                </span>
                            </p>
                            <p class="text-sm text-gray-500">Expires: {{ $subscription->ends_at ? $subscription->ends_at->format('M d, Y') : 'Never' }}</p>
                        </div>
                        <p class="font-bold">₹{{ number_format($subscription->amount, 2) }}</p>
                    </div>
                </div>
                @empty
                <p class="text-gray-500">No subscriptions</p>
                @endforelse
            </div>
        </div>

        <!-- Payments -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Payment History</h3>
            </div>
            <div class="p-6">
                @forelse($user->payments as $payment)
                <div class="mb-4 p-4 border rounded">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium">{{ $payment->payment_gateway_id }}</p>
                            <p class="text-sm text-gray-500">{{ $payment->created_at->format('M d, Y H:i') }}</p>
                            <p class="text-sm text-gray-500">Method: {{ ucfirst($payment->payment_method) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold">₹{{ number_format($payment->amount, 2) }}</p>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $payment->status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($payment->status) }}
                            </span>
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-gray-500">No payments</p>
                @endforelse
            </div>
        </div>

        <!-- Watch History -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Watch History</h3>
            </div>
            <div class="p-6">
                @forelse($user->watchHistory->take(10) as $history)
                <div class="mb-3 flex justify-between items-center">
                    <div>
                        <p class="font-medium text-sm">{{ $history->video->title ?? 'Video deleted' }}</p>
                        <p class="text-xs text-gray-500">{{ $history->updated_at->diffForHumans() }}</p>
                    </div>
                    <p class="text-sm text-gray-500">{{ round($history->watch_percentage) }}%</p>
                </div>
                @empty
                <p class="text-gray-500">No watch history</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection


