<?php
/**
 * Patch script: Add Admin Payment Gateway Settings UI.
 *
 * Adds:
 * - Admin controller: PaymentSettingsController
 * - View: admin/payments/settings.blade.php
 * - Routes:
 *    GET  /admin/payments/settings
 *    PUT  /admin/payments/settings
 *   (must be BEFORE payments resource route)
 * - Adds "Gateway Settings" button on Payments index page
 *
 * Allows updating Razorpay keys in `.env` (RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET).
 * NOTE: This writes secrets to server disk; ensure server access is restricted.
 *
 * Intended to run on the live server where Laravel lives at:
 *   /var/www/thaedal/api
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

function env_set(string $envPath, string $key, string $value): void {
    $value = str_replace(["\r", "\n"], '', $value);
    $escaped = preg_match('/\s|#|=|\"|\'/', $value) ? '"' . addcslashes($value, '"') . '"' : $value;

    $lines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];
    $found = false;
    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/u', $line)) {
            $lines[$i] = $key . '=' . $escaped;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $lines[] = $key . '=' . $escaped;
    }
    file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
}

$base = '/var/www/thaedal/api';
$routesPath = $base . '/routes/web.php';
$controllerPath = $base . '/app/Http/Controllers/Admin/PaymentSettingsController.php';
$viewPath = $base . '/resources/views/admin/payments/settings.blade.php';
$paymentsIndexViewPath = $base . '/resources/views/admin/payments/index.blade.php';

backup_file($routesPath);
backup_file($controllerPath);
backup_file($viewPath);
backup_file($paymentsIndexViewPath);

// 1) Controller
$controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class PaymentSettingsController extends Controller
{
    public function edit()
    {
        $keyId = (string) config('services.razorpay.key_id');
        $configured = !empty($keyId) && !empty((string) config('services.razorpay.key_secret'));

        return view('admin.payments.settings', [
            'configured' => $configured,
            'keyId' => $keyId,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'razorpay_key_id' => 'nullable|string|max:255',
            'razorpay_key_secret' => 'nullable|string|max:255',
        ]);

        $envPath = base_path('.env');
        if (!File::exists($envPath) || !File::isWritable($envPath)) {
            return back()->with('error', '.env not writable on server. Please update RAZORPAY_KEY_ID/RAZORPAY_KEY_SECRET manually.');
        }

        $keyId = trim((string) ($validated['razorpay_key_id'] ?? ''));
        $keySecret = trim((string) ($validated['razorpay_key_secret'] ?? ''));

        // Allow updating either field independently (leaving the other blank keeps existing)
        if ($keyId !== '') {
            $this->envSet($envPath, 'RAZORPAY_KEY_ID', $keyId);
        }
        if ($keySecret !== '') {
            $this->envSet($envPath, 'RAZORPAY_KEY_SECRET', $keySecret);
        }

        // Clear cached config so new env values are used immediately
        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            // Non-fatal; changes will apply on next deploy / restart
        }

        return back()->with('success', 'Payment gateway settings updated.');
    }

    private function envSet(string $envPath, string $key, string $value): void
    {
        $value = str_replace([\"\r\", \"\n\"], '', $value);
        $escaped = preg_match('/\\s|#|=|\"|\\'/', $value) ? '\"' . addcslashes($value, '\"') . '\"' : $value;

        $lines = File::exists($envPath) ? preg_split(\"/\\r\\n|\\n|\\r/\", File::get($envPath)) : [];
        $found = false;
        foreach ($lines as $i => $line) {
            if (preg_match('/^\\s*' . preg_quote($key, '/') . '\\s*=/u', (string) $line)) {
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

@mkdir(dirname($controllerPath), 0775, true);
file_put_contents($controllerPath, $controller);

// 2) Settings view
$view = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Payment Gateway Settings')
@section('page_title', 'Payment Gateway Settings')

@section('content')
<div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-start justify-between gap-6">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Razorpay Configuration</h2>
            <p class="text-gray-600 mt-1">Configure Razorpay keys used by the app for payments and by the admin dashboard for gateway stats.</p>
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

    <form method="POST" action="{{ route('admin.payments.settings.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700">RAZORPAY_KEY_ID</label>
            <input type="text"
                   name="razorpay_key_id"
                   value="{{ old('razorpay_key_id', $keyId) }}"
                   placeholder="rzp_test_..."
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            <p class="text-xs text-gray-500 mt-1">Safe to display; secret key is not shown.</p>
            @error('razorpay_key_id')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">RAZORPAY_KEY_SECRET</label>
            <input type="password"
                   name="razorpay_key_secret"
                   value=""
                   placeholder="Enter to update (leave blank to keep existing)"
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            <p class="text-xs text-gray-500 mt-1">Leave blank if you don’t want to change it.</p>
            @error('razorpay_key_secret')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
                Save
            </button>
            <a href="{{ route('admin.payments.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">Back to Payments →</a>
        </div>
    </form>

    <div class="mt-8 p-4 rounded bg-gray-50 text-sm text-gray-700">
        <div class="font-semibold mb-2">Notes</div>
        <ul class="list-disc pl-5 space-y-1">
            <li>If this server is production, use production Razorpay keys.</li>
            <li>If keys are configured, the “Razorpay Gross/Net” cards can be enabled later.</li>
        </ul>
    </div>
</div>
@endsection
BLADE;

@mkdir(dirname($viewPath), 0775, true);
file_put_contents($viewPath, $view);

// 3) Add a settings button to payments index (simple insertion after title)
$paymentsIndex = file_exists($paymentsIndexViewPath) ? file_get_contents($paymentsIndexViewPath) : '';
if ($paymentsIndex && strpos($paymentsIndex, "admin.payments.settings") === false) {
    // Insert a settings button near the filter bar (after the form open div)
    $needle = "<div class=\"mb-6 flex justify-between items-center\">";
    $replacement = $needle . "\n    <a href=\"{{ route('admin.payments.settings') }}\" class=\"px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700\">\n        Gateway Settings\n    </a>";
    $paymentsIndex = str_replace($needle, $replacement, $paymentsIndex);
    file_put_contents($paymentsIndexViewPath, $paymentsIndex);
}

// 4) Patch routes/web.php: add controller import + routes BEFORE payments resource
$routes = file_exists($routesPath) ? file_get_contents($routesPath) : '';
if ($routes) {
    if (strpos($routes, 'PaymentSettingsController') === false) {
        // Add use statement after PaymentController
        $routes = str_replace(
            "use App\\Http\\Controllers\\Admin\\PaymentController;\n",
            "use App\\Http\\Controllers\\Admin\\PaymentController;\nuse App\\Http\\Controllers\\Admin\\PaymentSettingsController;\n",
            $routes
        );
    }

    // Insert routes before the resource('payments', ...)
    if (strpos($routes, "payments/settings") === false) {
        $marker = "        // Payment Management\n        Route::resource('payments', PaymentController::class)->only(['index', 'show']);";
        $insert = "        // Payment Management\n        Route::get('payments/settings', [PaymentSettingsController::class, 'edit'])->name('payments.settings');\n        Route::put('payments/settings', [PaymentSettingsController::class, 'update'])->name('payments.settings.update');\n        Route::resource('payments', PaymentController::class)->only(['index', 'show']);";
        $routes = str_replace($marker, $insert, $routes);
    }

    file_put_contents($routesPath, $routes);
}

echo "Patched:\n- {$controllerPath}\n- {$viewPath}\n- {$routesPath}\n- {$paymentsIndexViewPath}\n";

