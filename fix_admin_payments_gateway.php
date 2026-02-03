<?php
/**
 * Patch script: Improve Admin Payments page:
 * - Fix filters to use filled() (avoid filtering when status/search are empty)
 * - Show Razorpay payment/order IDs clearly (no misleading truncation)
 * - Normalize method labels (UPI instead of "Upi"/"Ubi", Card, NetBanking, etc.)
 * - Prefer paid_at for displayed date when present
 *
 * Intended to run on the live server where Laravel lives at:
 *   /var/www/thaedal/api
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$controllerPath = '/var/www/thaedal/api/app/Http/Controllers/Admin/PaymentController.php';
$indexBladePath = '/var/www/thaedal/api/resources/views/admin/payments/index.blade.php';
$showBladePath = '/var/www/thaedal/api/resources/views/admin/payments/show.blade.php';

backup_file($controllerPath);
backup_file($indexBladePath);
backup_file($showBladePath);

$controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::query()->with(['user', 'subscription']);

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('razorpay_payment_id', 'like', "%{$search}%")
                  ->orWhere('razorpay_order_id', 'like', "%{$search}%")
                  ->orWhere('order_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('phone_number', 'like', "%{$search}%");
                  });
            });
        }

        $payments = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'total_revenue' => (float) Payment::query()->where('status', 'success')->sum('amount'),
            'pending_amount' => (float) Payment::query()->where('status', 'pending')->sum('amount'),
            'failed_count' => (int) Payment::query()->where('status', 'failed')->count(),
        ];

        return view('admin.payments.index', compact('payments', 'stats'));
    }

    public function show(Payment $payment)
    {
        $payment->load(['user', 'subscription']);
        return view('admin.payments.show', compact('payment'));
    }
}
PHP;

$indexBlade = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Payments')
@section('page_title', 'Payment Management')

@section('content')
<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Revenue</p>
        <p class="text-3xl font-bold text-green-600">₹{{ number_format($stats['total_revenue'], 2) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Pending Amount</p>
        <p class="text-3xl font-bold text-yellow-600">₹{{ number_format($stats['pending_amount'], 2) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Failed Payments</p>
        <p class="text-3xl font-bold text-red-600">{{ number_format($stats['failed_count']) }}</p>
    </div>
</div>

<div class="mb-6 flex justify-between items-center">
    <form action="{{ route('admin.payments.index') }}" method="GET" class="flex gap-2">
        <input type="text"
               name="search"
               value="{{ request('search') }}"
               placeholder="Search..."
               class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            <option value="">All Status</option>
            <option value="success" {{ request('status') == 'success' ? 'selected' : '' }}>Success</option>
            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
        </select>
        <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
            Filter
        </button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gateway</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($payments as $payment)
                @php
                    $rawMethod = strtolower((string)($payment->payment_method ?? ''));
                    $methodMap = [
                        'upi' => 'UPI',
                        'ubi' => 'UPI', // common typo
                        'card' => 'Card',
                        'netbanking' => 'NetBanking',
                        'wallet' => 'Wallet',
                        'emi' => 'EMI',
                        'bank_transfer' => 'Bank Transfer',
                        'bank transfer' => 'Bank Transfer',
                        'cash' => 'Cash',
                    ];
                    $methodLabel = $methodMap[$rawMethod] ?? ($rawMethod !== '' ? strtoupper($rawMethod) : 'N/A');
                    $date = $payment->paid_at ?? $payment->created_at;
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $payment->user->name ?? 'N/A' }}</div>
                        <div class="text-sm text-gray-500">{{ $payment->user->phone_number }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <div class="font-medium">Razorpay</div>
                        <div class="text-xs text-gray-500">
                            <div>Pay: {{ $payment->razorpay_payment_id ?? '—' }}</div>
                            <div>Order: {{ $payment->razorpay_order_id ?? $payment->order_id ?? '—' }}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ₹{{ number_format($payment->amount, 2) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        {{ $methodLabel }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            {{ $payment->status === 'success' ? 'bg-green-100 text-green-800' :
                               ($payment->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ ucfirst($payment->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $date->format('M d, Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('admin.payments.show', $payment) }}" class="text-blue-600 hover:text-blue-900">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">No payments found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6">
    {{ $payments->links() }}
</div>
@endsection
BLADE;

$showBlade = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Payment Details')
@section('page_title', 'Payment Details')

@section('content')
@php
    $rawMethod = strtolower((string)($payment->payment_method ?? ''));
    $methodMap = [
        'upi' => 'UPI',
        'ubi' => 'UPI',
        'card' => 'Card',
        'netbanking' => 'NetBanking',
        'wallet' => 'Wallet',
        'emi' => 'EMI',
        'bank_transfer' => 'Bank Transfer',
        'bank transfer' => 'Bank Transfer',
        'cash' => 'Cash',
    ];
    $methodLabel = $methodMap[$rawMethod] ?? ($rawMethod !== '' ? strtoupper($rawMethod) : 'N/A');
@endphp
<div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold">Payment #{{ $payment->order_id }}</h2>
            <p class="text-gray-500">{{ ($payment->paid_at ?? $payment->created_at)->format('M d, Y H:i') }}</p>
        </div>
        <span class="px-3 py-1 text-sm rounded-full {{ $payment->status === 'success' ? 'bg-green-100 text-green-800' : ($payment->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
            {{ ucfirst($payment->status) }}
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h3 class="font-semibold text-gray-700 mb-2">User</h3>
            @if($payment->user)
            <p>{{ $payment->user->name ?? 'N/A' }}</p>
            <p class="text-gray-500">{{ $payment->user->phone_number }}</p>
            <a href="{{ route('admin.users.show', $payment->user) }}" class="text-blue-600 hover:text-blue-800 text-sm">View User →</a>
            @else
            <p class="text-gray-500">User not found</p>
            @endif
        </div>

        <div>
            <h3 class="font-semibold text-gray-700 mb-2">Amount</h3>
            <p class="text-3xl font-bold">₹{{ number_format($payment->amount, 2) }}</p>
            <p class="text-gray-500">{{ $payment->currency }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-gray-700 mb-2">Payment Method</h3>
            <p>{{ $methodLabel }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-gray-700 mb-2">Description</h3>
            <p>{{ $payment->description ?? 'N/A' }}</p>
        </div>

        <div class="md:col-span-2">
            <h3 class="font-semibold text-gray-700 mb-2">Gateway</h3>
            <div class="bg-gray-50 rounded p-4 text-sm">
                <p><span class="text-gray-600">Provider:</span> Razorpay</p>
                <p><span class="text-gray-600">Order ID:</span> {{ $payment->razorpay_order_id ?? 'N/A' }}</p>
                <p><span class="text-gray-600">Payment ID:</span> {{ $payment->razorpay_payment_id ?? 'N/A' }}</p>
                @if($payment->paid_at)
                <p><span class="text-gray-600">Paid At:</span> {{ $payment->paid_at->format('M d, Y H:i:s') }}</p>
                @endif
                @if($payment->failure_reason)
                <p class="text-red-600"><span class="text-gray-600">Failure Reason:</span> {{ $payment->failure_reason }}</p>
                @endif
            </div>
        </div>

        @if($payment->subscription)
        <div class="md:col-span-2">
            <h3 class="font-semibold text-gray-700 mb-2">Linked Subscription</h3>
            <div class="bg-gray-50 rounded p-4">
                <p>Plan: {{ $payment->subscription->plan->name ?? 'N/A' }}</p>
                <p>Status: {{ ucfirst($payment->subscription->status) }}</p>
                <a href="{{ route('admin.subscriptions.show', $payment->subscription) }}" class="text-blue-600 hover:text-blue-800 text-sm">View Subscription →</a>
            </div>
        </div>
        @endif
    </div>
</div>

<div class="mt-6">
    <a href="{{ route('admin.payments.index') }}" class="text-blue-600 hover:text-blue-800">← Back to Payments</a>
</div>
@endsection
BLADE;

@mkdir(dirname($controllerPath), 0775, true);
@mkdir(dirname($indexBladePath), 0775, true);
@mkdir(dirname($showBladePath), 0775, true);

file_put_contents($controllerPath, $controller);
file_put_contents($indexBladePath, $indexBlade);
file_put_contents($showBladePath, $showBlade);

echo "Patched:\n- {$controllerPath}\n- {$indexBladePath}\n- {$showBladePath}\n";

