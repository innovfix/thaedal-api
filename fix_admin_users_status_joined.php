<?php
/**
 * Patch script: Improve Admin Users list
 * - Show registration date+time (created_at)
 * - Show derived status: Free / Trial Access / Premium Access
 *   based on latest valid subscription (active/trial + not expired) OR admin-forced is_subscribed.
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
                $q->select('id', 'user_id', 'status', 'ends_at', 'created_at')
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

$view = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Users')
@section('page_title', 'User Management')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <form action="{{ route('admin.users.index') }}" method="GET" class="flex gap-2">
        <input type="text"
               name="search"
               value="{{ request('search') }}"
               placeholder="Search users..."
               class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
        <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
            Search
        </button>
        @if(request('search'))
        <a href="{{ route('admin.users.index') }}" class="px-6 py-2 bg-gray-500 text-white font-semibold rounded-lg hover:bg-gray-600">
            Clear
        </a>
        @endif
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($users as $user)
                @php
                    $latest = $user->subscriptions->first();
                    $hasValid = $latest && in_array($latest->status, ['active','trial'], true) && optional($latest->ends_at)->gt(now());
                    $statusLabel = 'Free';
                    $statusClass = 'bg-gray-100 text-gray-800';

                    if ($hasValid) {
                        if ($latest->status === 'trial') {
                            $statusLabel = 'Trial Access';
                            $statusClass = 'bg-yellow-100 text-yellow-800';
                        } else {
                            $statusLabel = 'Premium Access';
                            $statusClass = 'bg-green-100 text-green-800';
                        }
                    } elseif ($user->is_subscribed) {
                        // Admin-forced premium without a valid subscription row
                        $statusLabel = 'Premium Access';
                        $statusClass = 'bg-green-100 text-green-800';
                    }
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $user->name ?? 'N/A' }}</div>
                        <div class="text-sm text-gray-500">{{ $user->email ?? 'No email' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $user->phone_number }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $user->created_at->format('M d, Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('admin.users.show', $user) }}" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                        <form action="{{ route('admin.users.toggle-subscription', $user) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-yellow-600 hover:text-yellow-900 mr-3">
                                {{ $user->is_subscribed ? 'Remove Sub' : 'Add Sub' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No users found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6">
    {{ $users->links() }}
</div>
@endsection
BLADE;

@mkdir(dirname($controllerPath), 0775, true);
@mkdir(dirname($viewPath), 0775, true);
file_put_contents($controllerPath, $controller);
file_put_contents($viewPath, $view);

echo "Patched:\n- {$controllerPath}\n- {$viewPath}\n";

