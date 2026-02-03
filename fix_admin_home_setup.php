<?php
/**
 * Patch script: Admin Home Setup (Featured Videos + Home Topics).
 *
 * Adds:
 * - Admin controller: HomeSetupController
 * - Views:
 *    resources/views/admin/home/index.blade.php
 *    resources/views/admin/home/edit_topic.blade.php
 * - Routes:
 *    GET  /admin/home
 *    POST /admin/home/featured/toggle/{video}
 *    GET  /admin/home/topics/{homeTopic}/edit
 *    POST /admin/home/topics/{homeTopic}/add-video
 *    POST /admin/home/topics/{homeTopic}/remove-video
 * - Sidebar link: Home Setup
 *
 * Intended to run on the live server where Laravel lives at:
 *   /var/www/thaedal/api
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$base = '/var/www/thaedal/api';
$controllerPath = $base . '/app/Http/Controllers/Admin/HomeSetupController.php';
$routesPath = $base . '/routes/web.php';
$layoutPath = $base . '/resources/views/admin/layouts/app.blade.php';
$homeDir = $base . '/resources/views/admin/home';
$homeIndexPath = $homeDir . '/index.blade.php';
$homeEditTopicPath = $homeDir . '/edit_topic.blade.php';

backup_file($controllerPath);
backup_file($routesPath);
backup_file($layoutPath);
backup_file($homeIndexPath);
backup_file($homeEditTopicPath);

// Controller
$controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeTopic;
use App\Models\Video;
use Illuminate\Http\Request;

class HomeSetupController extends Controller
{
    public function index(Request $request)
    {
        $featured = Video::query()
            ->with(['category', 'creator'])
            ->where('is_featured', true)
            ->latest('published_at')
            ->limit(50)
            ->get();

        $topics = HomeTopic::query()
            ->with(['category'])
            ->ordered()
            ->get();

        // Simple search for videos to add as featured or to a topic
        $q = $request->get('q');
        $videos = Video::query()
            ->with(['category', 'creator'])
            ->when($q, function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%");
            })
            ->latest('published_at')
            ->limit(25)
            ->get();

        return view('admin.home.index', compact('featured', 'topics', 'videos', 'q'));
    }

    public function toggleFeatured(Video $video)
    {
        $video->update(['is_featured' => !(bool) $video->is_featured]);
        return back()->with('success', 'Featured status updated.');
    }

    public function editTopic(HomeTopic $homeTopic)
    {
        $homeTopic->load(['category', 'videos.category', 'videos.creator']);

        $searchVideos = Video::query()
            ->with(['category', 'creator'])
            ->latest('published_at')
            ->limit(50)
            ->get();

        return view('admin.home.edit_topic', compact('homeTopic', 'searchVideos'));
    }

    public function addVideoToTopic(Request $request, HomeTopic $homeTopic)
    {
        $validated = $request->validate([
            'video_id' => 'required|uuid',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $videoId = $validated['video_id'];
        $sortOrder = (int) ($validated['sort_order'] ?? 0);

        // avoid duplicates
        if ($homeTopic->videos()->where('videos.id', $videoId)->exists()) {
            return back()->with('error', 'Video already added to this topic.');
        }

        $homeTopic->videos()->attach($videoId, ['sort_order' => $sortOrder]);
        return back()->with('success', 'Video added to topic.');
    }

    public function removeVideoFromTopic(Request $request, HomeTopic $homeTopic)
    {
        $validated = $request->validate([
            'video_id' => 'required|uuid',
        ]);

        $homeTopic->videos()->detach($validated['video_id']);
        return back()->with('success', 'Video removed from topic.');
    }
}
PHP;

@mkdir(dirname($controllerPath), 0775, true);
file_put_contents($controllerPath, $controller);

// Views
@mkdir($homeDir, 0775, true);

$indexView = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Home Setup')
@section('page_title', 'Home Setup')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Featured Videos</h3>
            <span class="text-sm text-gray-500">{{ $featured->count() }} items</span>
        </div>
        <div class="p-6 space-y-3">
            @forelse($featured as $v)
                <div class="flex items-center justify-between border rounded p-3">
                    <div class="flex items-center gap-3">
                        <img src="{{ $v->thumbnail_url }}" class="w-16 h-10 rounded object-cover" alt="">
                        <div>
                            <div class="font-medium text-gray-900">{{ \Illuminate\Support\Str::limit($v->title, 45) }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $v->category->name ?? 'N/A' }} • {{ $v->creator->name ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.home.featured.toggle', $v) }}">
                        @csrf
                        <button class="text-sm text-red-600 hover:text-red-800">Remove</button>
                    </form>
                </div>
            @empty
                <p class="text-gray-500">No featured videos yet.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Home Topics</h3>
            <p class="text-sm text-gray-500 mt-1">Manage topic sections shown on the app home screen.</p>
        </div>
        <div class="p-6 space-y-3">
            @forelse($topics as $t)
                <div class="flex items-center justify-between border rounded p-3">
                    <div>
                        <div class="font-medium text-gray-900">{{ $t->title }}</div>
                        <div class="text-xs text-gray-500">{{ $t->category->name ?? 'No category' }} • sort {{ $t->sort_order }}</div>
                    </div>
                    <a class="text-sm text-blue-600 hover:text-blue-800" href="{{ route('admin.home.topics.edit', $t) }}">Edit</a>
                </div>
            @empty
                <p class="text-gray-500">No home topics found.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="mt-6 bg-white rounded-lg shadow">
    <div class="p-6 border-b flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-800">Add / Toggle Featured</h3>
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search video title..."
                   class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            <button class="px-4 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">Search</button>
        </form>
    </div>
    <div class="p-6">
        <div class="space-y-3">
            @foreach($videos as $v)
                <div class="flex items-center justify-between border rounded p-3">
                    <div class="flex items-center gap-3">
                        <img src="{{ $v->thumbnail_url }}" class="w-16 h-10 rounded object-cover" alt="">
                        <div>
                            <div class="font-medium text-gray-900">{{ \Illuminate\Support\Str::limit($v->title, 45) }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $v->category->name ?? 'N/A' }} • {{ $v->creator->name ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.home.featured.toggle', $v) }}">
                        @csrf
                        <button class="text-sm {{ $v->is_featured ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' }}">
                            {{ $v->is_featured ? 'Unfeature' : 'Feature' }}
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
BLADE;
file_put_contents($homeIndexPath, $indexView);

$editTopicView = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Edit Home Topic')
@section('page_title', 'Edit Home Topic')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.home.index') }}" class="text-blue-600 hover:text-blue-800">← Back to Home Setup</a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-start justify-between gap-6">
        <div>
            <h2 class="text-xl font-bold text-gray-800">{{ $homeTopic->title }}</h2>
            <p class="text-sm text-gray-500 mt-1">{{ $homeTopic->category->name ?? 'No category' }}</p>
        </div>
        <span class="px-3 py-1 text-sm rounded-full {{ $homeTopic->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
            {{ $homeTopic->is_active ? 'Active' : 'Inactive' }}
        </span>
    </div>

    <div class="mt-6">
        <h3 class="font-semibold text-gray-800 mb-3">Videos in this topic</h3>
        <div class="space-y-3">
            @forelse($homeTopic->videos as $v)
                <div class="flex items-center justify-between border rounded p-3">
                    <div class="flex items-center gap-3">
                        <img src="{{ $v->thumbnail_url }}" class="w-16 h-10 rounded object-cover" alt="">
                        <div>
                            <div class="font-medium text-gray-900">{{ \Illuminate\Support\Str::limit($v->title, 45) }}</div>
                            <div class="text-xs text-gray-500">
                                sort {{ $v->pivot->sort_order ?? 0 }} • {{ $v->category->name ?? 'N/A' }} • {{ $v->creator->name ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.home.topics.removeVideo', $homeTopic) }}">
                        @csrf
                        <input type="hidden" name="video_id" value="{{ $v->id }}">
                        <button class="text-sm text-red-600 hover:text-red-800">Remove</button>
                    </form>
                </div>
            @empty
                <p class="text-gray-500">No videos in this topic yet.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-8 border-t pt-6">
        <h3 class="font-semibold text-gray-800 mb-3">Add a video</h3>
        <form method="POST" action="{{ route('admin.home.topics.addVideo', $homeTopic) }}" class="flex flex-col md:flex-row gap-3">
            @csrf
            <select name="video_id" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                @foreach($searchVideos as $v)
                    <option value="{{ $v->id }}">{{ \Illuminate\Support\Str::limit($v->title, 70) }}</option>
                @endforeach
            </select>
            <input type="number" name="sort_order" placeholder="sort order (0..9999)" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            <button class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">Add</button>
        </form>
    </div>
</div>
@endsection
BLADE;
file_put_contents($homeEditTopicPath, $editTopicView);

// Routes: import + insert routes in admin group
$routes = file_exists($routesPath) ? file_get_contents($routesPath) : '';
if ($routes) {
    if (strpos($routes, 'HomeSetupController') === false) {
        $routes = str_replace(
            "use App\\Http\\Controllers\\Admin\\PaymentSettingsController;\n",
            "use App\\Http\\Controllers\\Admin\\PaymentSettingsController;\nuse App\\Http\\Controllers\\Admin\\HomeSetupController;\n",
            $routes
        );
    }

    if (strpos($routes, "admin.home.index") === false && strpos($routes, "Route::get('home'") === false) {
        // Insert after dashboard route
        $marker = "        // Dashboard\n        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');";
        $insert = $marker . "\n\n        // Home Setup\n        Route::get('home', [HomeSetupController::class, 'index'])->name('home.index');\n        Route::post('home/featured/toggle/{video}', [HomeSetupController::class, 'toggleFeatured'])->name('home.featured.toggle');\n        Route::get('home/topics/{homeTopic}/edit', [HomeSetupController::class, 'editTopic'])->name('home.topics.edit');\n        Route::post('home/topics/{homeTopic}/add-video', [HomeSetupController::class, 'addVideoToTopic'])->name('home.topics.addVideo');\n        Route::post('home/topics/{homeTopic}/remove-video', [HomeSetupController::class, 'removeVideoFromTopic'])->name('home.topics.removeVideo');";
        $routes = str_replace($marker, $insert, $routes);
    }

    file_put_contents($routesPath, $routes);
}

// Sidebar link (insert after Dashboard)
$layout = file_exists($layoutPath) ? file_get_contents($layoutPath) : '';
if ($layout && strpos($layout, "admin.home.") === false) {
    $needle = "                    Dashboard\n                </a>\n";
    if (strpos($layout, $needle) !== false) {
        $link = <<<'HTML'

                <a href="{{ route('admin.home.index') }}"
                   class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-gold {{ request()->routeIs('admin.home.*') ? 'bg-gray-800 text-gold border-l-4 border-gold' : '' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/>
                    </svg>
                    Home Setup
                </a>
HTML;
        $layout = str_replace($needle, $needle . $link, $layout);
        file_put_contents($layoutPath, $layout);
    }
}

echo "Patched Home Setup.\n";
echo "- {$controllerPath}\n- {$routesPath}\n- {$layoutPath}\n- {$homeIndexPath}\n- {$homeEditTopicPath}\n";

