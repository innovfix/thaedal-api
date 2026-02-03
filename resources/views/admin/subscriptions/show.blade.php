@extends('admin.layouts.app')

@section('title', 'Subscription Details')
@section('page_title', 'Subscription Details')

@section('content')
<div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold">Subscription #{{ Str::limit($subscription->id, 8) }}</h2>
            <p class="text-gray-500">Created {{ $subscription->created_at->format('M d, Y H:i') }}</p>
        </div>
        <span class="px-3 py-1 text-sm rounded-full {{ $subscription->status === 'active' ? 'bg-green-100 text-green-800' : ($subscription->status === 'trial' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
            {{ ucfirst($subscription->status) }}
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h3 class="font-semibold text-gray-700 mb-2">User</h3>
            @if($subscription->user)
            <p>{{ $subscription->user->name ?? 'N/A' }}</p>
            <p class="text-gray-500">{{ $subscription->user->phone_number }}</p>
            <a href="{{ route('admin.users.show', $subscription->user) }}" class="text-blue-600 hover:text-blue-800 text-sm">View User →</a>
            @else
            <p class="text-gray-500">User not found</p>
            @endif
        </div>

        <div>
            <h3 class="font-semibold text-gray-700 mb-2">Plan</h3>
            @if($subscription->plan)
            @php
                $planAmount = (float) ($subscription->plan->price ?? 0);
                $planInt = (int) round($planAmount);
                if (in_array($planInt, [200, 9900, 29900], true) || $planAmount >= 1000) {
                    $planAmount = $planAmount / 100;
                }
            @endphp
            <p>{{ $subscription->plan->name }}</p>
            <p class="text-gray-500">₹{{ number_format($planAmount, 2) }}</p>
            @else
            <p class="text-gray-500">Plan not found</p>
            @endif
        </div>

        <div>
            <h3 class="font-semibold text-gray-700 mb-2">Duration</h3>
            <p>Started: {{ $subscription->starts_at?->format('M d, Y') ?? 'N/A' }}</p>
            <p>Ends: {{ $subscription->ends_at?->format('M d, Y') ?? 'N/A' }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-gray-700 mb-2">Auto Renew</h3>
            <p class="{{ $subscription->auto_renew ? 'text-green-600' : 'text-red-600' }}">
                {{ $subscription->auto_renew ? 'Enabled' : 'Disabled' }}
            </p>
            @if($subscription->next_billing_date)
            <p class="text-gray-500">Next billing: {{ $subscription->next_billing_date->format('M d, Y') }}</p>
            @endif
        </div>

        @if($subscription->razorpay_subscription_id)
        <div>
            <h3 class="font-semibold text-gray-700 mb-2">Razorpay</h3>
            <p class="text-xs text-gray-500">{{ $subscription->razorpay_subscription_id }}</p>
        </div>
        @endif
    </div>

    <div class="mt-6 pt-6 border-t">
        <form method="POST" action="{{ route('admin.subscriptions.update-status', $subscription) }}" class="flex items-center gap-4">
            @csrf
            <label class="text-gray-700">Update Status:</label>
            <select name="status" class="border rounded px-3 py-2">
                <option value="active" {{ $subscription->status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="trial" {{ $subscription->status === 'trial' ? 'selected' : '' }}>Trial</option>
                <option value="cancelled" {{ $subscription->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                <option value="expired" {{ $subscription->status === 'expired' ? 'selected' : '' }}>Expired</option>
            </select>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update</button>
        </form>
    </div>
</div>

<div class="mt-6">
    <a href="{{ route('admin.subscriptions.index') }}" class="text-blue-600 hover:text-blue-800">← Back to Subscriptions</a>
</div>
@endsection
