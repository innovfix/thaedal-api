<?php
/**
 * Patch script: Fix Admin Video create/edit/show fields mapping.
 *
 * Problems fixed:
 * - Edit form was using non-existent fields:
 *   - $video->duration_seconds (should be $video->duration)
 *   - $video->creator_name / $video->creator_thumbnail (should come from $video->creator relation)
 * - Video show page was using $video->status (non-existent). Use is_published/published_at instead.
 *
 * Intended to run on the live server where Laravel lives at:
 *   /var/www/thaedal/api
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$editPath = '/var/www/thaedal/api/resources/views/admin/videos/edit.blade.php';
$showPath = '/var/www/thaedal/api/resources/views/admin/videos/show.blade.php';
$controllerPath = '/var/www/thaedal/api/app/Http/Controllers/Admin/VideoController.php';

backup_file($editPath);
backup_file($showPath);
backup_file($controllerPath);

// Update controller edit() to eager-load creator for stable template access
$controller = file_exists($controllerPath) ? file_get_contents($controllerPath) : '';
if ($controller && strpos($controller, 'public function edit(Video $video)') !== false) {
    // best-effort: insert $video->load('creator'); after $categories line or at start of method
    $controller = preg_replace(
        '/public function edit\\(Video \\$video\\)\\s*\\{\\s*\\$categories = Category::all\\(\\);/s',
        "public function edit(Video \$video)\n    {\n        \$video->load('creator');\n        \$categories = Category::all();",
        $controller,
        1
    ) ?: $controller;
    file_put_contents($controllerPath, $controller);
}

// Rewrite edit blade with correct field mapping
$edit = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Edit Video')
@section('page_title', 'Edit Video')

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.videos.index') }}" class="text-blue-600 hover:text-blue-900">&larr; Back to Videos</a>
</div>

@php
    $creatorName = $video->creator->name ?? ($video->creator_name ?? '');
    $creatorThumb = $video->creator->avatar_url ?? ($video->creator_thumbnail ?? '');
    $durationSeconds = (int) ($video->duration ?? $video->duration_seconds ?? 0);
@endphp

<div class="bg-white rounded-lg shadow p-6 max-w-3xl">
    <form action="{{ route('admin.videos.update', $video) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="grid grid-cols-1 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                <input type="text" name="title" required value="{{ old('title', $video->title) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                @error('title')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                <textarea name="description" rows="4" required
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">{{ old('description', $video->description) }}</textarea>
                @error('description')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Video URL *</label>
                    <input type="url" name="video_url" required value="{{ old('video_url', $video->video_url) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                    @error('video_url')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Thumbnail URL *</label>
                    <input type="url" name="thumbnail_url" required value="{{ old('thumbnail_url', $video->thumbnail_url) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                    @error('thumbnail_url')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                    <select name="category_id" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $video->category_id) == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('category_id')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Duration (seconds) *</label>
                    <input type="number" name="duration_seconds" required value="{{ old('duration_seconds', $durationSeconds) }}" min="1"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                    @error('duration_seconds')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Creator Name *</label>
                    <input type="text" name="creator_name" required value="{{ old('creator_name', $creatorName) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                    @error('creator_name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Creator Thumbnail URL</label>
                    <input type="url" name="creator_thumbnail" value="{{ old('creator_thumbnail', $creatorThumb) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                    @error('creator_thumbnail')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tags (comma separated)</label>
                <input type="text" name="tags"
                       value="{{ old('tags', is_array($video->tags) ? implode(', ', $video->tags) : ($video->tags ?? '')) }}"
                       placeholder="tag1, tag2, tag3"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                @error('tags')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_premium" value="1" {{ old('is_premium', $video->is_premium) ? 'checked' : '' }}
                           class="mr-2">
                    <span class="text-sm font-medium text-gray-700">Premium Content</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $video->is_featured) ? 'checked' : '' }}
                           class="mr-2">
                    <span class="text-sm font-medium text-gray-700">Featured (Home)</span>
                </label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-3 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
                    Update Video
                </button>
                <a href="{{ route('admin.videos.index') }}" class="px-6 py-3 bg-gray-500 text-white font-semibold rounded-lg hover:bg-gray-600">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
BLADE;

file_put_contents($editPath, $edit);

// Fix show blade status section (replace entire file to avoid partial mismatches)
$show = <<<'BLADE'
@extends('admin.layouts.app')

@section('title', 'Video Details')
@section('page_title', 'Video Details')

@section('content')
@php
    $published = (bool)($video->is_published ?? false) && !empty($video->published_at);
@endphp
<div class="bg-white rounded-lg shadow">
    <div class="p-6 border-b flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold">{{ $video->title }}</h2>
            <p class="text-gray-500">{{ $video->created_at->format('M d, Y H:i') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.videos.edit', $video) }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Edit</a>
            <form method="POST" action="{{ route('admin.videos.destroy', $video) }}" onsubmit="return confirm('Delete this video?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Delete</button>
            </form>
        </div>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2">
                @if($video->thumbnail_url)
                <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="w-full rounded-lg mb-4">
                @endif
                <p class="text-gray-600">{{ $video->description }}</p>
            </div>
            <div class="space-y-4">
                <div>
                    <h3 class="font-semibold text-gray-700">Category</h3>
                    <p>{{ $video->category->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-700">Creator</h3>
                    <p>{{ $video->creator->name ?? $video->creator_name ?? 'N/A' }}</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-700">Duration</h3>
                    <p>{{ $video->duration_formatted ?? gmdate('H:i:s', (int)($video->duration ?? 0)) }}</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-700">Stats</h3>
                    <p>Views: {{ number_format($video->views_count ?? 0) }}</p>
                    <p>Likes: {{ number_format($video->likes_count ?? 0) }}</p>
                    <p>Saves: {{ number_format($video->saves_count ?? 0) }}</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-700">Status</h3>
                    <span class="px-2 py-1 text-xs rounded-full {{ $published ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                        {{ $published ? 'Published' : 'Draft' }}
                    </span>
                    @if($video->is_premium)
                    <span class="ml-2 px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">Premium</span>
                    @endif
                    @if($video->is_featured)
                    <span class="ml-2 px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Featured</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<div class="mt-6">
    <a href="{{ route('admin.videos.index') }}" class="text-blue-600 hover:text-blue-800">← Back to Videos</a>
</div>
@endsection
BLADE;

file_put_contents($showPath, $show);

echo "Patched:\n- {$controllerPath}\n- {$editPath}\n- {$showPath}\n";

