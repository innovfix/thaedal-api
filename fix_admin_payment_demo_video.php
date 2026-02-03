<?php
/**
 * Patch script: Add "Payment Demo / Paywall Video" upload + URL settings to admin.
 *
 * Extends:
 * - Admin\PaymentSettingsController (same /admin/payments/settings page)
 * - resources/views/admin/payments/settings.blade.php
 *
 * Stores into PaymentSetting:
 * - paywall_video_type: 'url' or 'file'
 * - paywall_video_url
 * - paywall_video_path (saved under storage/app/public/paywall_videos)
 *
 * Also allows editing:
 * - verification_fee_amount_paise
 * - autopay_amount_paise
 *
 * Intended to run on the live server where Laravel lives at:
 *   /var/www/thaedal/api
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$controllerPath = '/var/www/thaedal/api/app/Http/Controllers/Admin/PaymentSettingsController.php';
$viewPath = '/var/www/thaedal/api/resources/views/admin/payments/settings.blade.php';

backup_file($controllerPath);
backup_file($viewPath);

$controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentSettingsController extends Controller
{
    public function edit()
    {
        $keyId = (string) config('services.razorpay.key_id');
        $configured = $keyId !== '' && ((string) config('services.razorpay.key_secret')) !== '';

        $settings = PaymentSetting::current();

        return view('admin.payments.settings', [
            'configured' => $configured,
            'keyId' => $keyId,
            'settings' => $settings,
            'paywallVideoUrl' => $settings->paywallVideoUrl(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            // Razorpay env
            'razorpay_key_id' => 'nullable|string|max:255',
            'razorpay_key_secret' => 'nullable|string|max:255',

            // Pricing/settings (rupees)
            'verification_fee_amount' => 'nullable|numeric|min:0|max:999999',
            'autopay_amount' => 'nullable|numeric|min:0|max:999999',

            // Paywall demo video
            'paywall_video_type' => 'required|in:url,file',
            'paywall_video_url' => 'nullable|string|max:1000',
            'paywall_video_file' => 'nullable|file|mimetypes:video/mp4,video/webm,video/quicktime,video/x-m4v,application/octet-stream|max:51200',
            'remove_paywall_video' => 'nullable|boolean',
        ]);

        // 1) Update env keys (if provided)
        $envPath = base_path('.env');
        if (File::exists($envPath) && File::isWritable($envPath)) {
            $keyId = trim((string) ($validated['razorpay_key_id'] ?? ''));
            $keySecret = trim((string) ($validated['razorpay_key_secret'] ?? ''));

            if ($keyId !== '') {
                $this->envSet($envPath, 'RAZORPAY_KEY_ID', $keyId);
            }
            if ($keySecret !== '') {
                $this->envSet($envPath, 'RAZORPAY_KEY_SECRET', $keySecret);
            }
        }

        // 2) Update PaymentSetting
        $settings = PaymentSetting::current();

        $updates = [];

        if ($request->filled('verification_fee_amount')) {
            $updates['verification_fee_amount_paise'] = (int) round(((float) $validated['verification_fee_amount']) * 100);
        }
        if ($request->filled('autopay_amount')) {
            $updates['autopay_amount_paise'] = (int) round(((float) $validated['autopay_amount']) * 100);
        }

        $type = (string) $validated['paywall_video_type'];
        $remove = (bool) ($validated['remove_paywall_video'] ?? false);

        if ($remove) {
            if (!empty($settings->paywall_video_path) && Storage::disk('public')->exists($settings->paywall_video_path)) {
                Storage::disk('public')->delete($settings->paywall_video_path);
            }
            $updates['paywall_video_path'] = null;
            $updates['paywall_video_url'] = null;
            $updates['paywall_video_type'] = 'url';
        } else {
            if ($type === 'url') {
                $url = trim((string) ($validated['paywall_video_url'] ?? ''));
                if ($url === '') {
                    return back()->withInput()->with('error', 'Please provide a Paywall video URL, or choose File upload.');
                }
                $updates['paywall_video_type'] = 'url';
                $updates['paywall_video_url'] = $url;
                // keep existing file path (but not used)
            } else {
                // file upload selected
                if ($request->hasFile('paywall_video_file')) {
                    $file = $request->file('paywall_video_file');
                    $ext = $file->getClientOriginalExtension() ?: 'mp4';
                    $name = 'paywall_videos/paywall_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.' . $ext;
                    $path = $file->storeAs('paywall_videos', basename($name), 'public');

                    // delete old
                    if (!empty($settings->paywall_video_path) && Storage::disk('public')->exists($settings->paywall_video_path)) {
                        Storage::disk('public')->delete($settings->paywall_video_path);
                    }

                    $updates['paywall_video_type'] = 'file';
                    $updates['paywall_video_path'] = $path; // relative to public disk
                    $updates['paywall_video_url'] = null;
                } else {
                    // If no file provided, keep existing file if present
                    if (empty($settings->paywall_video_path)) {
                        return back()->withInput()->with('error', 'Please upload a video file (MP4/WebM/MOV), or switch to URL.');
                    }
                    $updates['paywall_video_type'] = 'file';
                }
            }
        }

        // bump pricing version when any of these change
        if (!empty($updates)) {
            $updates['pricing_updated_at'] = now();
            $updates['pricing_version'] = ((int) ($settings->pricing_version ?? 0)) + 1;
            $settings->update($updates);
        }

        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            // ignore
        }

        return back()->with('success', 'Payment settings updated.');
    }

    private function envSet(string $envPath, string $key, string $value): void
    {
        $value = str_replace(["\r", "\n"], '', $value);
        $needsQuotes = (bool) preg_match('~[\s#="]~', $value);
        $escaped = $needsQuotes ? '"' . addcslashes($value, '"') . '"' : $value;

        $lines = File::exists($envPath)
            ? preg_split("~\r\n|\n|\r~", (string) File::get($envPath))
            : [];

        $found = false;
        foreach ($lines as $i => $line) {
            if (preg_match('~^\s*' . preg_quote($key, '~') . '\s*=~u', (string) $line)) {
                $lines[$i] = $key . '=' . $escaped;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $lines[] = $key . '=' . $escaped;
        }

        File::put($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
    }
}
PHP;

$view = <<<'BLADE'
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
BLADE;

@mkdir(dirname($controllerPath), 0775, true);
@mkdir(dirname($viewPath), 0775, true);
file_put_contents($controllerPath, $controller);
file_put_contents($viewPath, $view);

echo "Patched:\n- {$controllerPath}\n- {$viewPath}\n";

