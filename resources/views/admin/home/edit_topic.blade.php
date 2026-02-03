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