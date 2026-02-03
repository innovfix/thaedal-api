@extends('admin.layouts.app')

@section('title', 'Payment Settings')
@section('page_title', 'Payment Settings')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-start justify-between gap-6">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Razorpay Configuration</h2>
                <p class="text-gray-600 mt-1">Configure Razorpay keys used by the app for payments and by the admin dashboard.</p>
            </div>
            <div>
                @if($configured)
                    <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800">Configured</span>
                @else
                    <span class="px-3 py-1 text-sm rounded-full bg-red-100 text-red-800">Not configured</span>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mt-4 p-3 rounded bg-green-50 text-green-700 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.payments.settings.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">RAZORPAY_KEY_ID</label>
                    <input type="text"
                           name="razorpay_key_id"
                           value="{{ old('razorpay_key_id', $keyId) }}"
                           placeholder="rzp_live_..."
                           class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                    @error('razorpay_key_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">RAZORPAY_KEY_SECRET</label>
                    <input type="password"
                           name="razorpay_key_secret"
                           value=""
                           placeholder="Enter to update (leave blank to keep existing)"
                           class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                    @error('razorpay_key_secret')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-800">Pricing</h3>
                <p class="text-sm text-gray-500 mt-1">These values are used by the app paywall.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Verification fee (₹)</label>
                        <input type="number" step="0.01" name="verification_fee_amount"
                               value="{{ old('verification_fee_amount', ((int)($settings->verification_fee_amount_paise ?? 200))/100) }}"
                               class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                        @error('verification_fee_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Autopay amount (₹)</label>
                        <input type="number" step="0.01" name="autopay_amount"
                               value="{{ old('autopay_amount', ((int)($settings->autopay_amount_paise ?? 9900))/100) }}"
                               class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                        @error('autopay_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="text-xs text-gray-500 mt-2">
                    Current version: {{ (int)($settings->pricing_version ?? 0) }} • Updated: {{ optional($settings->pricing_updated_at)->toDateTimeString() ?? '—' }}
                </div>
            </div>

            <div class="border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-800">Payment Demo / Paywall Video</h3>
                <p class="text-sm text-gray-500 mt-1">This video is shown on the paywall screen in the app.</p>

                <div class="mt-3 text-sm text-gray-700">
                    <span class="font-medium">Demo video views:</span>
                    <span class="ml-1">{{ (int) ($settings->paywall_video_view_count ?? 0) }}</span>
                </div>

                @if($paywallVideoUrl)
                    <div class="mt-3 p-3 rounded bg-gray-50 text-sm">
                        <div class="text-gray-700 font-medium">Current video:</div>
                        <div class="text-gray-600 break-all">{{ $paywallVideoUrl }}</div>
                    </div>
                @endif

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Source</label>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="paywall_video_type" value="url" {{ old('paywall_video_type', ($settings->paywall_video_type ?? 'url') === 'url' ? 'url' : 'file') === 'url' ? 'checked' : '' }}>
                            URL
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="paywall_video_type" value="file" {{ old('paywall_video_type', ($settings->paywall_video_type ?? 'url') === 'url' ? 'url' : 'file') === 'file' ? 'checked' : '' }}>
                            Upload file
                        </label>
                    </div>
                    @error('paywall_video_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Video URL</label>
                        <input type="text" name="paywall_video_url"
                               value="{{ old('paywall_video_url', $settings->paywall_video_url) }}"
                               placeholder="https://..."
                               class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                        @error('paywall_video_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Upload video file</label>
                        <input type="file" name="paywall_video_file" accept="video/*"
                               class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                        <p class="text-xs text-gray-500 mt-1">Max 50MB. MP4/WebM/MOV recommended.</p>
                        @error('paywall_video_file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="remove_paywall_video" value="1">
                        Remove current paywall video
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
                    Save
                </button>
                <a href="{{ route('admin.payments.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">Back to Payments →</a>
            </div>
        </form>
    </div>
</div>
@endsection