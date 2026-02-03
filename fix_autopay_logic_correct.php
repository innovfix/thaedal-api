<?php
/**
 * Fix autopay display logic to check REAL Razorpay autopay status
 * 
 * REAL Autopay ON = razorpay_subscription_id exists + status active/trial + auto_renew = true
 * NOT Autopay = razorpay_subscription_id is NULL OR status is cancelled/expired
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
                    
                    // Subscription details
                    $subStatus = $latest ? $latest->status : 'none';
                    $hasRazorpaySub = $latest && !empty($latest->razorpay_subscription_id);
                    
                    // REAL autopay check:
                    // - Must have razorpay_subscription_id (linked to Razorpay)
                    // - Must have auto_renew = true
                    // - Must NOT be cancelled/expired
                    $realAutopayOn = $hasRazorpaySub 
                        && (bool) ($latest->auto_renew ?? false)
                        && in_array($subStatus, ['active', 'trial', 'authenticated']);
                    
                    // Check if subscription is valid (not expired)
                    $hasValidSub = $latest && 
                        in_array($subStatus, ['active', 'trial']) && 
                        (is_null($latest->ends_at) || optional($latest->ends_at)->gt(now()));
                    
                    // Access window calculation
                    $accessEndsAt = null;
                    if ($latest && $latest->ends_at) {
                        $accessEndsAt = $latest->ends_at;
                    } elseif ($hasPaid && $user->verification_fee_paid_at) {
                        $accessEndsAt = $user->verification_fee_paid_at->copy()->addDays(7);
                    }
                    $stillHasAccess = $accessEndsAt && $accessEndsAt->gt(now());
                    
                    // Determine correct status based on REAL data
                    if ($adminForced && !$hasPaid) {
                        // Admin manually granted premium (no payment)
                        $statusLabel = 'Premium';
                        $statusClass = 'bg-green-100 text-green-800';
                        $statusIcon = '👑';
                        $statusNote = 'Admin';
                    } elseif ($hasPaid && $realAutopayOn) {
                        // Cat2: Paid + REAL Razorpay autopay ON
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
                        $statusNote = 'Lapsed';
                    } else {
                        // Cat1: Never paid
                        $statusLabel = 'Free';
                        $statusClass = 'bg-gray-100 text-gray-800';
                        $statusIcon = '🆓';
                        $statusNote = 'Cat1';
                    }
                    
                    // Autopay display logic
                    $autopayDisplay = '—';
                    $autopayClass = 'text-gray-400';
                    $autopayNote = '';
                    
                    if (!$hasPaid) {
                        $autopayDisplay = '—';
                        $autopayNote = 'Not paid';
                    } elseif ($realAutopayOn) {
                        $autopayDisplay = '🔄 ON';
                        $autopayClass = 'bg-green-100 text-green-800';
                        $autopayNote = $accessEndsAt ? 'Next: ' . $accessEndsAt->format('M d') : '';
                    } elseif ($hasRazorpaySub && $subStatus === 'cancelled') {
                        $autopayDisplay = '⏹️ OFF';
                        $autopayClass = 'bg-red-100 text-red-800';
                        $autopayNote = 'Cancelled';
                    } elseif ($hasRazorpaySub && !($latest->auto_renew ?? false)) {
                        $autopayDisplay = '⏹️ OFF';
                        $autopayClass = 'bg-orange-100 text-orange-800';
                        $autopayNote = 'Disabled';
                    } else {
                        // Paid but no Razorpay subscription linked
                        $autopayDisplay = '⚠️ None';
                        $autopayClass = 'bg-yellow-100 text-yellow-800';
                        $autopayNote = 'Not set up';
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
                        <div class="flex flex-col gap-1">
                            <span class="px-2 py-1 inline-flex text-xs font-semibold rounded-full {{ $autopayClass }}">
                                {{ $autopayDisplay }}
                            </span>
                            @if($autopayNote)
                                <span class="text-xs text-gray-500">{{ $autopayNote }}</span>
                            @endif
                        </div>
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
    <h4 class="text-sm font-semibold text-gray-700 mb-3">Legend:</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <h5 class="text-xs font-semibold text-gray-600 mb-2">Status:</h5>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded">🆓 Free (Cat1) - Never paid</span>
                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">⏳ Trial (Cat3) - Paid, no autopay</span>
                <span class="px-2 py-1 bg-green-100 text-green-800 rounded">💎 Premium (Cat2) - Paid + autopay</span>
                <span class="px-2 py-1 bg-red-100 text-red-800 rounded">⏸️ Expired - Access ended</span>
            </div>
        </div>
        <div>
            <h5 class="text-xs font-semibold text-gray-600 mb-2">Autopay:</h5>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="px-2 py-1 bg-green-100 text-green-800 rounded">🔄 ON - Razorpay autopay active</span>
                <span class="px-2 py-1 bg-red-100 text-red-800 rounded">⏹️ OFF - Cancelled/disabled</span>
                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">⚠️ None - Not linked to Razorpay</span>
            </div>
        </div>
    </div>
</div>
@endsection
BLADE;

file_put_contents($viewPath, $view);
echo "✅ Fixed autopay logic!\n\n";
echo "New autopay check:\n";
echo "- 🔄 ON = razorpay_subscription_id exists + auto_renew=true + status active/trial\n";
echo "- ⏹️ OFF = Cancelled or auto_renew=false\n";
echo "- ⚠️ None = Paid ₹2 but Razorpay autopay not set up\n";
