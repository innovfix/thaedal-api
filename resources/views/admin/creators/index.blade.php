@extends('admin.layouts.app')

@section('title', 'Creators')
@section('page_title', 'Creator Management')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <form action="{{ route('admin.creators.index') }}" method="GET" class="flex gap-2">
        <input type="text" 
               name="search" 
               value="{{ request('search') }}" 
               placeholder="Search creators..."
               class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
        <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
            Search
        </button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($creators as $creator)
    <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
        <div class="flex items-center mb-4">
            @if($creator->avatar_url)
            <img src="{{ $creator->avatar_url }}" alt="" class="w-16 h-16 rounded-full mr-4 object-cover">
            @else
            <div class="w-16 h-16 rounded-full bg-gray-200 mr-4 flex items-center justify-center">
                <span class="text-2xl text-gray-500">{{ substr($creator->name, 0, 1) }}</span>
            </div>
            @endif
            <div>
                <h3 class="font-semibold text-lg">{{ $creator->name }}</h3>
                <p class="text-sm text-gray-500">{{ $creator->videos_count ?? 0 }} videos</p>
            </div>
        </div>
        <div class="flex justify-between items-center text-sm text-gray-600">
            <span>{{ number_format($creator->videos_sum_views_count ?? 0) }} views</span>
            <a href="{{ route('admin.creators.show', $creator->id) }}" class="text-blue-600 hover:text-blue-900 font-medium">
                View Details →
            </a>
        </div>
    </div>
    @empty
    <div class="col-span-3 text-center py-12">
        <p class="text-gray-500">No creators found</p>
    </div>
    @endforelse
</div>

<div class="mt-6">
    {{ $creators->links() }}
</div>
@endsection
