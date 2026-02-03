@extends('admin.layouts.app')

@section('title', 'Create Creator')
@section('page_title', 'Create Creator')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        @if(session('error'))
            <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
        @endif
        <form action="{{ route('admin.creators.store') }}" method="POST" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700">Creator Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Bio (optional)</label>
                <textarea name="bio" rows="3"
                          class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">{{ old('bio') }}</textarea>
                @error('bio')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Avatar URL (optional)</label>
                <input type="text" name="avatar_url" value="{{ old('avatar_url') }}"
                       placeholder="https://..."
                       class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                @error('avatar_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Cover URL (optional)</label>
                <input type="text" name="cover_url" value="{{ old('cover_url') }}"
                       placeholder="https://..."
                       class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                @error('cover_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-6">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_verified" value="1" {{ old('is_verified') ? 'checked' : '' }}>
                    Verified
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    Active
                </label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90">
                    Create
                </button>
                <a href="{{ route('admin.creators.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">Back to Creators →</a>
            </div>
        </form>
    </div>
</div>
@endsection
