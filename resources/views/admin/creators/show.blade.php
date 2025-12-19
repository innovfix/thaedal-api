@extends('admin.layouts.app')

@section('title', 'Creator Details')
@section('page_title', 'Creator: ' . $creator->name)

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.creators.index') }}" class="text-blue-600 hover:text-blue-900">&larr; Back to Creators</a>
</div>

<!-- Creator Info -->
<div class="bg-white rounded-lg shadow p-6 mb-8">
    <div class="flex items-center">
        @if($creator->avatar_url)
        <img src="{{ $creator->avatar_url }}" alt="" class="w-24 h-24 rounded-full mr-6 object-cover">
        @else
        <div class="w-24 h-24 rounded-full bg-gray-200 mr-6 flex items-center justify-center">
            <span class="text-4xl text-gray-500">{{ substr($creator->name, 0, 1) }}</span>
        </div>
        @endif
        <div>
            <h2 class="text-2xl font-bold text-gray-800">{{ $creator->name }}</h2>
            @if($creator->bio)
            <p class="text-gray-600 mt-2">{{ $creator->bio }}</p>
            @endif
            <div class="flex gap-4 mt-2 text-sm text-gray-500">
                @if($creator->is_verified)
                <span class="text-green-600">✓ Verified</span>
                @endif
                <span>Joined {{ $creator->created_at->format('M d, Y') }}</span>
            </div>
        </div>
    </div>
</div>

<!-- Creator Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Videos</p>
        <p class="text-3xl font-bold text-gray-800">{{ number_format($stats['total_videos']) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Views</p>
        <p class="text-3xl font-bold text-gray-800">{{ number_format($stats['total_views'] ?? 0) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Likes</p>
        <p class="text-3xl font-bold text-gray-800">{{ number_format($stats['total_likes'] ?? 0) }}</p>
    </div>
</div>

<!-- Videos -->
<div class="bg-white rounded-lg shadow">
    <div class="p-6 border-b">
        <h3 class="text-lg font-semibold">Videos by {{ $creator->name }}</h3>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Video</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Views</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Likes</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($videos as $video)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <img src="{{ $video->thumbnail_url }}" alt="" class="w-20 h-12 object-cover rounded mr-3">
                                <div class="text-sm font-medium text-gray-900">{{ Str::limit($video->title, 40) }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $video->category->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ number_format($video->views_count) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ number_format($video->likes_count) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <a href="{{ route('admin.videos.edit', $video) }}" class="text-blue-600 hover:text-blue-900">Edit</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No videos</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-6">
            {{ $videos->links() }}
        </div>
    </div>
</div>
@endsection
