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