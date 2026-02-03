<?php
/**
 * Update users index to show autopay status clearly
 */

$viewPath = '/var/www/thaedal/api/resources/views/admin/users/index.blade.php';

$view = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Users')
@section('page_title', 'User Management')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <form action="{{ route('admin.users.index') }}" method="GET" class="flex gap-2">
        <input type="text"
               name="search"
               value="{{ request('search') }}"
               placeholder="Search users..."
               class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
        <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
            Search
        </button>
        @if(request('search'))
        <a href="{{ route('admin.users.index') }}" class="px-6 py-2 bg-gray-500 text-white font-semibold rounded-lg hover:bg-gray-600">
            Clear
        </a>
        @endif
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Autopay</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($users as $user)
                @php
                    // Get latest subscription
                    $latest = $user->subscriptions->first();
                    
                    // Check if user actually paid ₹2 verification fee
                    $hasPaid = (bool) ($user->has_paid_verification_fee ?? false);
                    
                    // Admin can force premium via is_subscribed flag
                    $adminForced = (bool) $user->is_subscribed;
                    
                    // Check subscription status
                    $hasValidSub = $latest && 
                        in_array($latest->status, ['active', 'trial']) && 
                        (is_null($latest->ends_at) || optional($latest->ends_at)->gt(now()));
                    
                    // Autopay status
                    $autopayOn = $latest && (bool) ($latest->auto_renew ?? false);
                    $subStatus = $latest ? $latest->status : 'none';
                    
                    // Determine access window
                    $accessEndsAt = null;
                    if ($latest && $latest->ends_at) {
                        $accessEndsAt = $latest->ends_at;
                    } elseif ($hasPaid && $user->verification_fee_paid_at) {
                        $accessEndsAt = $user->verification_fee_paid_at->copy()->addDays(7);
                    }
                    $stillHasAccess = $accessEndsAt && $accessEndsAt->gt(now());
                    
                    // Determine correct status
                    if ($adminForced) {
                        $statusLabel = 'Premium';
                        $statusClass = 'bg-green-100 text-green-800';
                        $statusIcon = '👑';
                        $statusNote = 'Admin granted';
                    } elseif ($hasPaid && $hasValidSub && $autopayOn) {
                        // Cat2: Paid + Autopay ON
                        $statusLabel = 'Premium';
                        $statusClass = 'bg-green-100 text-green-800';
                        $statusIcon = '💎';
                        $statusNote = 'Cat2';
                    } elseif ($hasPaid && $stillHasAccess) {
                        // Cat3: Paid but autopay OFF, still has access
                        $statusLabel = 'Trial Access';
                        $statusClass = 'bg-yellow-100 text-yellow-800';
                        $statusIcon = '⏳';
                        $statusNote = 'Cat3';
                    } elseif ($hasPaid && !$stillHasAccess) {
                        // Paid but access expired
                        $statusLabel = 'Expired';
                        $statusClass = 'bg-red-100 text-red-800';
                        $statusIcon = '⏸️';
                        $statusNote = 'Needs re-enable';
                    } else {
                        // Cat1: Never paid
                        $statusLabel = 'Free';
                        $statusClass = 'bg-gray-100 text-gray-800';
                        $statusIcon = '🆓';
                        $statusNote = 'Cat1';
                    }
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $user->name ?? 'N/A' }}</div>
                        <div class="text-sm text-gray-500">{{ $user->email ?? 'No email' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $user->phone_number }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col gap-1">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                                {{ $statusIcon }} {{ $statusLabel }}
                            </span>
                            @if($hasPaid)
                                <span class="text-xs text-green-600">₹2 Paid ✓</span>
                            @endif
                            <span class="text-xs text-gray-400">{{ $statusNote }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($hasPaid)
                            @if($autopayOn)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    🔄 ON
                                </span>
                                @if($accessEndsAt)
                                    <div class="text-xs text-gray-500 mt-1">
                                        Next: {{ $accessEndsAt->format('M d') }}
                                    </div>
                                @endif
                            @else
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                    ⏹️ OFF
                                </span>
                                @if($subStatus === 'cancelled')
                                    <div class="text-xs text-red-500 mt-1">Cancelled</div>
                                @endif
                            @endif
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $user->created_at->format('M d, Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('admin.users.show', $user) }}" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                        <form action="{{ route('admin.users.toggle-subscription', $user) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-yellow-600 hover:text-yellow-900 mr-3">
                                {{ $user->is_subscribed ? 'Remove Sub' : 'Add Sub' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No users found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6">
    {{ $users->links() }}
</div>

<!-- Status Legend -->
<div class="mt-4 p-4 bg-gray-50 rounded-lg">
    <h4 class="text-sm font-semibold text-gray-700 mb-2">Status Legend:</h4>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 text-xs">
        <div class="p-2 bg-white rounded border">
            <span class="font-semibold">🆓 Free (Cat1)</span>
            <p class="text-gray-500">Never paid ₹2</p>
        </div>
        <div class="p-2 bg-white rounded border">
            <span class="font-semibold">⏳ Trial (Cat3)</span>
            <p class="text-gray-500">Paid ₹2, autopay OFF</p>
        </div>
        <div class="p-2 bg-white rounded border">
            <span class="font-semibold">💎 Premium (Cat2)</span>
            <p class="text-gray-500">Paid ₹2 + autopay ON</p>
        </div>
        <div class="p-2 bg-white rounded border">
            <span class="font-semibold">👑 Premium</span>
            <p class="text-gray-500">Admin granted</p>
        </div>
        <div class="p-2 bg-white rounded border">
            <span class="font-semibold">⏸️ Expired</span>
            <p class="text-gray-500">Paid but access ended</p>
        </div>
        <div class="p-2 bg-white rounded border">
            <span class="font-semibold">Autopay</span>
            <p class="text-gray-500">🔄 ON / ⏹️ OFF</p>
        </div>
    </div>
</div>
@endsection
BLADE;

file_put_contents($viewPath, $view);
echo "✅ Updated users index with Autopay column!\n";
