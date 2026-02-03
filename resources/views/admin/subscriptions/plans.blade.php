@extends('admin.layouts.app')

@section('title', 'Subscription Plans')
@section('page_title', 'Subscription Plans')

@section('content')
<div class="bg-white rounded-lg shadow">
    <div class="p-6 border-b">
        <h2 class="text-xl font-bold">All Plans</h2>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @forelse($plans as $plan)
            <div class="border rounded-lg p-6 {{ $plan->is_popular ? 'border-blue-500 border-2' : '' }}">
                @if($plan->is_popular)
                <span class="bg-blue-500 text-white px-2 py-1 text-xs rounded">Popular</span>
                @endif
                <h3 class="text-xl font-bold mt-2">{{ $plan->name }}</h3>
                <p class="text-3xl font-bold mt-4">₹{{ number_format($plan->price, 0) }}<span class="text-sm font-normal text-gray-500">/{{ $plan->duration_type }}</span></p>
                <p class="text-gray-500 mt-2">{{ $plan->description }}</p>
                <ul class="mt-4 space-y-2">
                    <li class="flex items-center text-sm">
                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        {{ $plan->duration_days }} days access
                    </li>
                    @if($plan->trial_days > 0)
                    <li class="flex items-center text-sm">
                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        {{ $plan->trial_days }} days trial
                    </li>
                    @endif
                </ul>
                <div class="mt-4 pt-4 border-t">
                    <p class="text-xs text-gray-500">Razorpay Plan: {{ $plan->razorpay_plan_id ?? 'Not configured' }}</p>
                    <p class="text-xs text-gray-500">Status: {{ $plan->is_active ? 'Active' : 'Inactive' }}</p>
                </div>
            </div>
            @empty
            <p class="col-span-3 text-center text-gray-500 py-8">No plans configured</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
