<?php
/**
 * Hotfix: PaymentSettingsController syntax error.
 * Rewrites the controller with safe string/regex literals (no broken escaping).
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
backup_file($controllerPath);

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
        $configured = $keyId !== '' && ((string) config('services.razorpay.key_secret')) !== '';

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

        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            // Non-fatal; changes will apply on next restart/deploy
        }

        return back()->with('success', 'Payment gateway settings updated.');
    }

    private function envSet(string $envPath, string $key, string $value): void
    {
        $value = str_replace(["\r", "\n"], '', $value);

        // Quote if value contains whitespace or special env characters
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

@mkdir(dirname($controllerPath), 0775, true);
file_put_contents($controllerPath, $controller);

echo "Hotfixed: {$controllerPath}\n";

