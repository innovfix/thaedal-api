@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="text-sm text-gray-500">Selected date: <span class="font-semibold text-gray-800">{{ $selectedDateLabel }}</span></div>
    <form method="GET" class="flex items-center gap-2">
        <label class="text-sm text-gray-600">Filter date</label>
        <input type="date" name="date" value="{{ $selectedDateInput }}" class="border rounded px-3 py-2 text-sm">
        <button type="submit" class="px-4 py-2 text-sm bg-gray-900 text-white rounded">Apply</button>
    </form>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Totals -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Totals</h3>
        </div>
        <div class="p-6">
            <ul class="space-y-3 text-sm">
                <li class="flex items-center justify-between"><span>Total users</span><span class="font-semibold">{{ number_format($stats['total_users']) }}</span></li>
                <li class="flex items-center justify-between"><span>Total free tier users</span><span class="font-semibold">{{ number_format($stats['total_free_users']) }}</span></li>
                <li class="flex items-center justify-between"><span>Premium users total</span><span class="font-semibold">{{ number_format($stats['premium_users_total']) }}</span></li>
                <li class="flex items-center justify-between"><span>Total video category</span><span class="font-semibold">{{ number_format($stats['total_categories']) }}</span></li>
                <li class="flex items-center justify-between"><span>Total videos</span><span class="font-semibold">{{ number_format($stats['total_videos']) }}</span></li>
                <li class="flex items-center justify-between"><span>Total payment</span><span class="font-semibold">{{ number_format($stats['total_payment'], 2) }}</span></li>
                <li class="flex items-center justify-between"><span>Demo video views</span><span class="font-semibold">{{ number_format($stats['paywall_video_views']) }}</span></li>
            </ul>
            @if(!empty($stats['razorpay_error']))
                <p class="text-xs text-red-600 mt-3">{{ $stats['razorpay_error'] }}</p>
            @endif
        </div>
    </div>

    <!-- Selected Date -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Date-wise ({{ $selectedDateLabel }})</h3>
        </div>
        <div class="p-6">
            <ul class="space-y-3 text-sm">
                <li class="flex items-center justify-between"><span>Today register user</span><span class="font-semibold">{{ number_format($stats['today_register_users']) }}</span></li>
                <li class="flex items-center justify-between"><span>Today trial user</span><span class="font-semibold">{{ number_format($stats['today_trial_users']) }}</span></li>
                <li class="flex items-center justify-between"><span>Today premium user</span><span class="font-semibold">{{ number_format($stats['today_premium_users']) }}</span></li>
                <li class="flex items-center justify-between"><span>Today payment</span><span class="font-semibold">{{ number_format($stats['today_payment'], 2) }}</span></li>
            </ul>
        </div>
    </div>
    <!-- Install/Uninstall Stats -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800"> App Install/Uninstall</h3>
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="date" value="{{ $selectedDateInput }}">
                <input type="date" name="install_date" value="{{ $installDateInput }}" class="border rounded px-3 py-2 text-sm">
                <button type="submit" class="px-3 py-2 text-sm bg-gray-900 text-white rounded">Apply</button>
            </form>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-lg border border-gray-200 p-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Total</h4>
                    <ul class="space-y-3 text-sm">
                        <li class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                Installed
                            </span>
                            <span class="font-semibold text-green-600">{{ number_format($stats["total_installed_users"]) }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                Uninstalled
                            </span>
                            <span class="font-semibold text-red-600">{{ number_format($stats["total_uninstalled_users"]) }}</span>
                        </li>
                    </ul>
                </div>
                <div class="rounded-lg border border-gray-200 p-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Selected Date</h4>
                    <ul class="space-y-3 text-sm">
                        <li class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                Installed
                            </span>
                            <span class="font-semibold text-green-600">{{ number_format($stats["today_installed_users"]) }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                Uninstalled
                            </span>
                            <span class="font-semibold text-red-600">{{ number_format($stats["today_uninstalled_users"]) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Autopay -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Autopay</h3>
        </div>
        <div class="p-6">
            <ul class="space-y-3 text-sm">
                <li class="flex items-center justify-between"><span>Total autopay enabled user</span><span class="font-semibold">{{ number_format($stats['autopay_enabled_users']) }}</span></li>
                <li class="flex items-center justify-between"><span>Total autopay disabled user count</span><span class="font-semibold">{{ number_format($stats['autopay_disabled_users']) }}</span></li>
            </ul>
        </div>
    </div>
</div>


<!-- App Timing Scatter -->
<div class="mt-8">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">App Timing & User Metrics</h3>
                <p class="text-sm text-gray-500 mt-1">Time graph + user metrics filters in one place.</p>
            </div>
            <form method="GET" class="flex items-center gap-2 flex-wrap">
                <input type="hidden" name="date" value="{{ $selectedDateInput }}">
                @if($autopayDateFilter)
                    <input type="hidden" name="autopay_date" value="{{ $autopayDateFilter }}">
                @endif
                <label class="text-sm text-gray-600">From</label>
                <input type="date" name="scatter_from" value="{{ $scatterFromInput }}" class="border rounded px-3 py-2 text-sm">
                <label class="text-sm text-gray-600">To</label>
                <input type="date" name="scatter_to" value="{{ $scatterToInput }}" class="border rounded px-3 py-2 text-sm">
                <select name="graph" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="today" {{ $graphType === 'today' ? 'selected' : '' }}>Today Registered / Trial / Premium</option>
                    <option value="autopay" {{ $graphType === 'autopay' ? 'selected' : '' }}>Autopay Yes / No</option>
                    <option value="total" {{ $graphType === 'total' ? 'selected' : '' }}>Total Registered / Trial / Premium</option>
                </select>
                <button type="submit" class="px-4 py-2 text-sm bg-gray-900 text-white rounded">Apply</button>
            </form>
        </div>
        <div class="p-6">
            <canvas id="appTimingScatter" height="140"></canvas>
        </div>
    </div>
</div>

<!-- Autopay Forecast Section -->
<div class="mt-8">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Upcoming Autopay Forecast</h3>
                <p class="text-sm text-gray-500 mt-1">Date-wise expected autopay charges (next 2 weeks)</p>
            </div>
            @if($autopayDateFilter)
                <a href="{{ route('admin.dashboard') }}" class="text-sm text-blue-600 hover:underline"> Show all dates</a>
            @endif
        </div>

        <!-- Summary Table -->
        @if($autopaySummary->count() > 0)
        <div class="p-6 border-b">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Summary by Date</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Date</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-600">Users</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">Expected Amount</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-600">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($autopaySummary as $row)
                        @php
                            $isToday = \Carbon\Carbon::parse($row->charge_date)->isToday();
                            $rowClass = $isToday ? 'bg-green-50' : '';
                        @endphp
                        <tr class="{{ $rowClass }} hover:bg-gray-50">
                            <td class="px-4 py-2">
                                {{ \Carbon\Carbon::parse($row->charge_date)->format('D, M d, Y') }}
                                @if($isToday)
                                    <span class="ml-2 px-2 py-0.5 text-xs bg-green-600 text-white rounded">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center font-semibold">{{ $row->users_count }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-green-700">{{ number_format($row->expected_amount) }}</td>
                            <td class="px-4 py-2 text-center">
                                <a href="?autopay_date={{ $row->charge_date }}" class="text-blue-600 hover:underline text-xs">View Users</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100">
                        <tr>
                            <td class="px-4 py-2 font-semibold">Total</td>
                            <td class="px-4 py-2 text-center font-semibold">{{ $autopaySummary->sum('users_count') }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-green-700">{{ number_format($autopaySummary->sum('expected_amount')) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @else
        <div class="p-6 text-center text-gray-500">
            No upcoming autopay charges found.
        </div>
        @endif

        <!-- Detailed User List -->
        @if($upcomingAutopay->count() > 0)
        <div class="p-6">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">
                @if($autopayDateFilter)
                    Users with autopay on {{ \Carbon\Carbon::parse($autopayDateFilter)->format('M d, Y') }}
                @else
                    Upcoming Autopay Users (Next 50)
                @endif
            </h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">User</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Phone</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Paid On</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Next Charge</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Amount</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($upcomingAutopay as $sub)
                        @php
                            $chargeIsToday = $sub->next_billing_date && \Carbon\Carbon::parse($sub->next_billing_date)->isToday();
                        @endphp
                        <tr class="{{ $chargeIsToday ? 'bg-green-50' : '' }} hover:bg-gray-50">
                            <td class="px-3 py-2">{{ $sub->user_name ?: 'N/A' }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $sub->phone_number ?: '-' }}</td>
                            <td class="px-3 py-2 text-xs">
                                {{ $sub->verification_fee_paid_at ? \Carbon\Carbon::parse($sub->verification_fee_paid_at)->format('M d, Y') : '-' }}
                            </td>
                            <td class="px-3 py-2 text-xs">
                                {{ $sub->next_billing_date ? \Carbon\Carbon::parse($sub->next_billing_date)->format('M d, Y') : '-' }}
                                @if($chargeIsToday)
                                    <span class="ml-1 text-green-600 font-semibold">Today</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format($sub->plan_price) }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 text-xs rounded-full 
                                    {{ $sub->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ ucfirst($sub->status) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3"></script>
<script>
(() => {
    const installs = @json($installScatter);
    const opens = @json($engagementScatter);
    const ctx = document.getElementById('appTimingScatter');
    if (!ctx) return;

    const bucketHours = (values, from, to) => {
        const start = new Date(from);
        const end = new Date(to);
        const buckets = [];
        for (let d = new Date(start); d <= end; d.setHours(d.getHours() + 1)) {
            buckets.push(d.toISOString());
        }
        const counts = new Map(buckets.map((b) => [b, 0]));
        values.forEach((t) => {
            const dt = new Date(t);
            if (isNaN(dt.getTime())) return;
            const key = new Date(dt.getFullYear(), dt.getMonth(), dt.getDate(), dt.getHours()).toISOString();
            if (counts.has(key)) {
                counts.set(key, counts.get(key) + 1);
            }
        });
        return Array.from(counts.entries()).map(([x, y]) => ({ x, y }));
    };

    const from = "{{ $scatterFromInput }}T00:00:00";
    const to = "{{ $scatterToInput }}T23:59:59";

    const installSeries = bucketHours(installs, from, to);
    const openSeries = bucketHours(opens, from, to);

    new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Installs',
                    data: installSeries,
                    borderColor: '#2563EB',
                    backgroundColor: 'rgba(37, 99, 235, 0.15)',
                    tension: 0.2,
                    pointRadius: 2
                },
                {
                    label: 'App Opens',
                    data: openSeries,
                    borderColor: '#16A34A',
                    backgroundColor: 'rgba(22, 163, 74, 0.15)',
                    tension: 0.2,
                    pointRadius: 2
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    type: 'time',
                    time: { unit: 'hour', tooltipFormat: 'MMM d, HH:mm' },
                    title: { display: true, text: 'Time' }
                },
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Count' }
                }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
})();
</script>


@endpush
