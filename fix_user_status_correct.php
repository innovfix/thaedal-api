<?php
/**
 * Fix user status logic in admin panel to correctly show:
 * - Free: Never paid ₹2 (Cat1_New)
 * - Trial Access: Paid ₹2 but autopay OFF (Cat3_AutopayOffAfter2) - within access window
 * - Premium Access: Paid ₹2 + autopay ON (Cat2_AutopayOn) OR admin-forced
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($users as $user)
                @php
                    // CORRECT STATUS LOGIC based on actual payment
                    $latest = $user->subscriptions->first();
                    
                    // Check if user actually paid ₹2 verification fee
                    $hasPaid = (bool) ($user->has_paid_verification_fee ?? false);
                    
                    // Admin can force premium via is_subscribed flag
                    $adminForced = (bool) $user->is_subscribed;
                    
                    // Check subscription status
                    $hasValidSub = $latest && 
                        in_array($latest->status, ['active', 'trial']) && 
                        (is_null($latest->ends_at) || optional($latest->ends_at)->gt(now()));
                    
                    $autopayOn = $latest && (bool) ($latest->auto_renew ?? false);
                    
                    // Determine correct status based on ACTUAL payment
                    // Cat2: Paid + Autopay ON = Premium
                    // Cat3: Paid + Autopay OFF = Trial Access (within window)
                    // Cat1: Never paid = Free
                    
                    if ($adminForced) {
                        // Admin manually granted premium
                        $statusLabel = 'Premium Access';
                        $statusClass = 'bg-green-100 text-green-800';
                        $statusIcon = '👑';
                    } elseif ($hasPaid && $hasValidSub && $autopayOn) {
                        // Cat2: Paid ₹2 + Active subscription + Autopay ON
                        $statusLabel = 'Premium Access';
                        $statusClass = 'bg-green-100 text-green-800';
                        $statusIcon = '💎';
                    } elseif ($hasPaid) {
                        // Cat3: Paid ₹2 but autopay is OFF (or subscription expired)
                        // Check if still within 7-day access window
                        $accessEndsAt = null;
                        if ($latest && $latest->ends_at) {
                            $accessEndsAt = $latest->ends_at;
                        } elseif ($user->verification_fee_paid_at) {
                            $accessEndsAt = $user->verification_fee_paid_at->copy()->addDays(7);
                        }
                        $stillHasAccess = $accessEndsAt && $accessEndsAt->gt(now());
                        
                        if ($stillHasAccess) {
                            $statusLabel = 'Trial Access';
                            $statusClass = 'bg-yellow-100 text-yellow-800';
                            $statusIcon = '⏳';
                        } else {
                            $statusLabel = 'Expired';
                            $statusClass = 'bg-red-100 text-red-800';
                            $statusIcon = '⏸️';
                        }
                    } else {
                        // Cat1: Never paid ₹2 = Free user
                        $statusLabel = 'Free';
                        $statusClass = 'bg-gray-100 text-gray-800';
                        $statusIcon = '🆓';
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
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                            {{ $statusIcon }} {{ $statusLabel }}
                        </span>
                        @if($hasPaid)
                            <div class="text-xs text-green-600 mt-1">₹2 Paid ✓</div>
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
                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No users found</td>
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
    <div class="flex flex-wrap gap-4 text-xs">
        <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded">🆓 Free = Never paid ₹2</span>
        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">⏳ Trial Access = Paid ₹2, autopay OFF</span>
        <span class="px-2 py-1 bg-green-100 text-green-800 rounded">💎 Premium = Paid ₹2 + autopay ON</span>
        <span class="px-2 py-1 bg-green-100 text-green-800 rounded">👑 Premium = Admin granted</span>
        <span class="px-2 py-1 bg-red-100 text-red-800 rounded">⏸️ Expired = Paid but access ended</span>
    </div>
</div>
@endsection
BLADE;

file_put_contents($viewPath, $view);
echo "✅ Fixed user status logic!\n\n";
echo "New Status Logic:\n";
echo "- 🆓 Free = has_paid_verification_fee = false (Cat1_New)\n";
echo "- ⏳ Trial Access = Paid ₹2 + autopay OFF, within access window (Cat3)\n";
echo "- 💎 Premium = Paid ₹2 + autopay ON (Cat2)\n";
echo "- 👑 Premium = Admin manually granted (is_subscribed=true)\n";
echo "- ⏸️ Expired = Paid ₹2 but access window ended\n";
