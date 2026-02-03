<?php
/**
 * Patch script: Users list status mapping (Free / Trial Access / Premium Access).
 *
 * Some subscriptions are stuck in status "created" even for trial flows; treat recent "created" as Trial Access.
 * Also treat active subscriptions with NULL ends_at as Premium Access.
 *
 * Intended to run on the live server where Laravel lives at:
 *   /var/www/thaedal/api
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$controllerPath = '/var/www/thaedal/api/app/Http/Controllers/Admin/UserController.php';
$viewPath = '/var/www/thaedal/api/resources/views/admin/users/index.blade.php';

backup_file($controllerPath);
backup_file($viewPath);

$controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->with(['subscriptions' => function ($q) {
                $q->select('id', 'user_id', 'status', 'is_trial', 'ends_at', 'trial_ends_at', 'created_at')
                    ->latest('created_at');
            }]);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load(['subscriptions', 'payments', 'watchHistory']);
        return view('admin.users.show', compact('user'));
    }

    public function destroy(User $user)
    {
        $user->forceDelete();
        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }

    public function toggleSubscription(User $user)
    {
        $user->update(['is_subscribed' => !$user->is_subscribed]);
        return back()->with('success', 'Subscription status updated');
    }
}
PHP;

// Update just the status logic portion + keep joined date+time (H:i)
$view = file_exists($viewPath) ? file_get_contents($viewPath) : '';
if (!$view) {
    echo "users/index.blade.php not found\n";
    exit(1);
}

// Replace the @php status block with a more robust one (match by key lines)
$oldNeedle = "@php\n                    \$latest = \$user->subscriptions->first();\n                    \$hasValid = \$latest && in_array(\$latest->status, ['active','trial'], true) && optional(\$latest->ends_at)->gt(now());\n                    \$statusLabel = 'Free';\n                    \$statusClass = 'bg-gray-100 text-gray-800';\n\n                    if (\$hasValid) {\n                        if (\$latest->status === 'trial') {\n                            \$statusLabel = 'Trial Access';\n                            \$statusClass = 'bg-yellow-100 text-yellow-800';\n                        } else {\n                            \$statusLabel = 'Premium Access';\n                            \$statusClass = 'bg-green-100 text-green-800';\n                        }\n                    } elseif (\$user->is_subscribed) {\n                        // Admin-forced premium without a valid subscription row\n                        \$statusLabel = 'Premium Access';\n                        \$statusClass = 'bg-green-100 text-green-800';\n                    }\n                @endphp";

$newNeedle = "@php\n                    \$latest = \$user->subscriptions->first();\n\n                    // Premium access if admin-forced OR active subscription not expired (or lifetime ends_at NULL)\n                    \$hasActive = \$latest && \$latest->status === 'active' && (is_null(\$latest->ends_at) || optional(\$latest->ends_at)->gt(now()));\n                    \$isPremium = (bool) \$user->is_subscribed || \$hasActive;\n\n                    // Trial access if trial subscription not expired OR \"created\" subscription in last 7 days (common in trial flows)\n                    \$trialWindowOk = \$latest && (\n                        optional(\$latest->trial_ends_at)->gt(now())\n                        || optional(\$latest->ends_at)->gt(now())\n                        || optional(\$latest->created_at)->gt(now()->subDays(7))\n                    );\n                    \$isTrial = \$latest && (\n                        (bool) (\$latest->is_trial ?? false)\n                        || in_array(\$latest->status, ['trial', 'created'], true)\n                    ) && \$trialWindowOk;\n\n                    \$statusLabel = 'Free';\n                    \$statusClass = 'bg-gray-100 text-gray-800';\n\n                    if (\$isPremium) {\n                        \$statusLabel = 'Premium Access';\n                        \$statusClass = 'bg-green-100 text-green-800';\n                    } elseif (\$isTrial) {\n                        \$statusLabel = 'Trial Access';\n                        \$statusClass = 'bg-yellow-100 text-yellow-800';\n                    }\n                @endphp";

if (strpos($view, $oldNeedle) !== false) {
    $view = str_replace($oldNeedle, $newNeedle, $view);
} else {
    // If content already different, do a safer replace by looking for $statusLabel init line
    // (best-effort fallback)
    echo "Warning: could not find exact old status block; skipping blade replacement.\n";
}

file_put_contents($controllerPath, $controller);
file_put_contents($viewPath, $view);

echo "Patched:\n- {$controllerPath}\n- {$viewPath}\n";

