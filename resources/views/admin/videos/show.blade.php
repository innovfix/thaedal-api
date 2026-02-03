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