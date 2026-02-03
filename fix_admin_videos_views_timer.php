<?php
/**
 * Patch script: Fix Admin Videos list:
 * - show correct duration (uses videos.duration; falls back to duration_seconds if present)
 * - show correct creator (uses relation creator->name; falls back to creator_name if present)
 * - show views from videos.views_count; falls back to watch_history count when views_count is empty/zero
 * - eager load creator and add watch_history count in controller
 *
 * Intended to run on the live server where Laravel lives at:
 *   /var/www/thaedal/api
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$controllerPath = '/var/www/thaedal/api/app/Http/Controllers/Admin/VideoController.php';
$bladePath = '/var/www/thaedal/api/resources/views/admin/videos/index.blade.php';

backup_file($controllerPath);
backup_file($bladePath);

$controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Video;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        // Include creator for display and watchHistory count as a fallback "views" metric
        $query = Video::query()
            ->with(['category', 'creator'])
            ->withCount('watchHistory');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    // Backward compatibility: some older DBs may still have creator_name column
                    ->orWhere('creator_name', 'like', "%{$search}%")
                    ->orWhereHas('creator', function ($qc) use ($search) {
                        $qc->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->get('category'));
        }

        $videos = $query->latest()->paginate(20)->withQueryString();
        $categories = Category::query()->orderBy('name')->get();

        return view('admin.videos.index', compact('videos', 'categories'));
    }

    public function create()
    {
        $categories = Category::all();
        return view('admin.videos.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'video_url' => 'required|url',
            'thumbnail_url' => 'required|url',
            'category_id' => 'required|exists:categories,id',
            'creator_name' => 'required|string|max:255',
            'creator_thumbnail' => 'nullable|url',
            'duration_seconds' => 'required|integer|min:1',
            'is_premium' => 'boolean',
            'tags' => 'nullable|string',
        ]);

        // Find or create creator
        $creator = \App\Models\Creator::firstOrCreate(
            ['name' => $validated['creator_name']],
            [
                'avatar_url' => $validated['creator_thumbnail'] ?? null,
                'bio' => null,
                'subscribers_count' => 0,
                'total_views' => 0,
                'is_active' => true,
            ]
        );

        // Map duration_seconds to duration
        $validated['duration'] = $validated['duration_seconds'];
        unset($validated['duration_seconds']);

        // Set creator_id
        $validated['creator_id'] = $creator->id;
        unset($validated['creator_name'], $validated['creator_thumbnail']);

        // Set published status so video appears to users
        $validated['is_published'] = true;
        $validated['published_at'] = now();

        // Handle tags
        if (isset($validated['tags'])) {
            $validated['tags'] = array_map('trim', explode(',', $validated['tags']));
        }

        Video::create($validated);

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video created successfully');
    }

    public function edit(Video $video)
    {
        $categories = Category::all();
        return view('admin.videos.edit', compact('video', 'categories'));
    }

    public function update(Request $request, Video $video)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'video_url' => 'required|url',
            'thumbnail_url' => 'required|url',
            'category_id' => 'required|exists:categories,id',
            'creator_name' => 'required|string|max:255',
            'creator_thumbnail' => 'nullable|url',
            'duration_seconds' => 'required|integer|min:1',
            'is_premium' => 'boolean',
            'tags' => 'nullable|string',
        ]);

        // Find or create creator
        $creator = \App\Models\Creator::firstOrCreate(
            ['name' => $validated['creator_name']],
            [
                'avatar_url' => $validated['creator_thumbnail'] ?? null,
                'bio' => null,
                'subscribers_count' => 0,
                'total_views' => 0,
                'is_active' => true,
            ]
        );

        // Map duration_seconds to duration
        $validated['duration'] = $validated['duration_seconds'];
        unset($validated['duration_seconds']);

        // Set creator_id
        $validated['creator_id'] = $creator->id;
        unset($validated['creator_name'], $validated['creator_thumbnail']);

        // Ensure video remains published (or set published_at if not set)
        if (!$video->published_at) {
            $validated['published_at'] = now();
        }
        $validated['is_published'] = true;

        // Handle tags
        if (isset($validated['tags'])) {
            $validated['tags'] = array_map('trim', explode(',', $validated['tags']));
        }

        $video->update($validated);

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video updated successfully');
    }

    public function destroy(Video $video)
    {
        $video->delete();
        return redirect()->route('admin.videos.index')
            ->with('success', 'Video deleted successfully');
    }
}
PHP;

$blade = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Videos')
@section('page_title', 'Video Management')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <form action="{{ route('admin.videos.index') }}" method="GET" class="flex gap-2">
        <input type="text"
               name="search"
               value="{{ request('search') }}"
               placeholder="Search videos..."
               class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
        <select name="category" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
            <option value="">All Categories</option>
            @foreach($categories as $category)
            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                {{ $category->name }}
            </option>
            @endforeach
        </select>
        <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
            Filter
        </button>
    </form>
    <a href="{{ route('admin.videos.create') }}" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
        + Add Video
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Video</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Creator</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($videos as $video)
                @php
                    $durationSeconds = (int) ($video->duration ?? $video->duration_seconds ?? 0);
                    $durationLabel = $durationSeconds > 0 ? gmdate('H:i:s', $durationSeconds) : '—';
                    $views = max((int) ($video->views_count ?? 0), (int) ($video->watch_history_count ?? 0));
                    $creatorName = $video->creator->name ?? ($video->creator_name ?? 'N/A');
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <img src="{{ $video->thumbnail_url }}" alt="" class="w-20 h-12 object-cover rounded mr-3">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ Str::limit($video->title, 50) }}</div>
                                <div class="text-sm text-gray-500">{{ $durationLabel }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $video->category->name ?? 'N/A' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $creatorName }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ number_format($views) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $video->is_premium ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                            {{ $video->is_premium ? 'Premium' : 'Free' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('admin.videos.edit', $video) }}" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                        <form action="{{ route('admin.videos.destroy', $video) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No videos found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6">
    {{ $videos->links() }}
</div>
@endsection
BLADE;

@mkdir(dirname($controllerPath), 0775, true);
@mkdir(dirname($bladePath), 0775, true);
file_put_contents($controllerPath, $controller);
file_put_contents($bladePath, $blade);

echo "Patched:\n- {$controllerPath}\n- {$bladePath}\n";

