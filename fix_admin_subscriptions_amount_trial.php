<?php
/**
 * Patch script: Fix Admin Subscriptions list:
 * - Amount was showing ₹0.00 because `subscriptions.amount` column doesn't exist.
 * - Add payments() relation on Subscription model (needed by controller/view).
 * - In admin list, show:
 *   - Paid amount (sum of successful payments) when available
 *   - Otherwise plan price
 *   - If trial, also show verification fee amount
 * - Add Trial/Regular badge
 * - Fix filters: use filled() to avoid filtering on empty strings
 *
 * Intended to run on the live server where Laravel lives at:
 *   /var/www/thaedal/api
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$subscriptionModelPath = '/var/www/thaedal/api/app/Models/Subscription.php';
$controllerPath = '/var/www/thaedal/api/app/Http/Controllers/Admin/SubscriptionController.php';
$bladePath = '/var/www/thaedal/api/resources/views/admin/subscriptions/index.blade.php';

backup_file($subscriptionModelPath);
backup_file($controllerPath);
backup_file($bladePath);

$subscriptionModel = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'is_trial',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'auto_renew',
        'payment_method_id',
        'next_billing_date',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_trial' => 'boolean',
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'auto_renew' => 'boolean',
            'next_billing_date' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrial($query)
    {
        return $query->where('status', 'trial');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeValid($query)
    {
        return $query->whereIn('status', ['active', 'trial'])
            ->where('ends_at', '>', now());
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->whereIn('status', ['active', 'trial'])
            ->whereBetween('ends_at', [now(), now()->addDays($days)]);
    }

    // Helpers
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial']) && $this->ends_at > now();
    }

    public function isExpired(): bool
    {
        return $this->ends_at <= now();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function daysRemaining(): int
    {
        return max(0, now()->diffInDays($this->ends_at, false));
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'auto_renew' => false,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function renew(): void
    {
        $this->update([
            'status' => 'active',
            'starts_at' => $this->ends_at,
            'ends_at' => $this->ends_at->addDays($this->plan->duration_days),
            'next_billing_date' => $this->ends_at->addDays($this->plan->duration_days),
        ]);
    }
}
PHP;

$controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $settings = PaymentSetting::current();
        $verificationFee = ((int) ($settings->verification_fee_amount_paise ?? 200)) / 100;

        $query = Subscription::query()
            ->with(['user', 'plan'])
            ->withSum(['payments as paid_amount' => function ($q) {
                $q->where('status', 'success');
            }], 'amount');

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $subscriptions = $query->latest()->paginate(20)->withQueryString();

        return view('admin.subscriptions.index', compact('subscriptions', 'verificationFee'));
    }

    public function show(Subscription $subscription)
    {
        $subscription->load(['user', 'plan', 'payments']);
        return view('admin.subscriptions.show', compact('subscription'));
    }

    public function updateStatus(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,cancelled,expired,paused',
        ]);

        $subscription->update($validated);

        return back()->with('success', 'Subscription status updated');
    }

    public function plans()
    {
        $plans = SubscriptionPlan::all();
        return view('admin.subscriptions.plans', compact('plans'));
    }
}
PHP;

$blade = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Subscriptions')
@section('page_title', 'Subscription Management')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <form action="{{ route('admin.subscriptions.index') }}" method="GET" class="flex gap-2">
        <input type="text"
               name="search"
               value="{{ request('search') }}"
               placeholder="Search..."
               class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            <option value="">All Status</option>
            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
            <option value="trial" {{ request('status') == 'trial' ? 'selected' : '' }}>Trial</option>
            <option value="created" {{ request('status') == 'created' ? 'selected' : '' }}>Created</option>
            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Expired</option>
            <option value="paused" {{ request('status') == 'paused' ? 'selected' : '' }}>Paused</option>
        </select>
        <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
            Filter
        </button>
    </form>
    <a href="{{ route('admin.subscriptions.plans') }}" class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
        Manage Plans
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($subscriptions as $subscription)
                @php
                    $paid = (float) ($subscription->paid_amount ?? 0);
                    $planPrice = (float) ($subscription->plan->price ?? 0);
                    $displayAmount = $paid > 0 ? $paid : $planPrice;
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $subscription->user->name ?? 'N/A' }}</div>
                        <div class="text-sm text-gray-500">{{ $subscription->user->phone_number }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div class="flex items-center gap-2">
                            <span>{{ $subscription->plan->name ?? 'N/A' }}</span>
                            @if($subscription->is_trial)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Trial</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Regular</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div class="font-medium">₹{{ number_format($displayAmount, 2) }}</div>
                        <div class="text-xs text-gray-500">
                            @if($paid > 0)
                                Paid
                            @else
                                Plan price
                            @endif
                            @if($subscription->is_trial)
                                • Trial fee: ₹{{ number_format((float)($verificationFee ?? 2.0), 2) }}
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            {{ $subscription->status === 'active' ? 'bg-green-100 text-green-800' : ($subscription->status === 'trial' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                            {{ ucfirst($subscription->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $subscription->ends_at ? $subscription->ends_at->format('M d, Y') : 'Never' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="text-blue-600 hover:text-blue-900">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No subscriptions found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6">
    {{ $subscriptions->links() }}
</div>
@endsection
BLADE;

@mkdir(dirname($subscriptionModelPath), 0775, true);
@mkdir(dirname($controllerPath), 0775, true);
@mkdir(dirname($bladePath), 0775, true);

file_put_contents($subscriptionModelPath, $subscriptionModel);
file_put_contents($controllerPath, $controller);
file_put_contents($bladePath, $blade);

echo "Patched:\n- {$subscriptionModelPath}\n- {$controllerPath}\n- {$bladePath}\n";

