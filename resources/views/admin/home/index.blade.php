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
        <form method="GET" class="flex items-center gap-2 flex-wrap">
            <div class="relative">
                <input type="text" name="q" value="{{ $q }}" placeholder="Search video title..."
                       data-suggest-type="home"
                       class="js-suggest-input px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                <div class="js-suggest-list absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg hidden z-20"></div>
            </div>
            <select name="category_id" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                <option value="">All Categories</option>
                @foreach($categories ?? [] as $category)
                <option value="{{ $category->id }}" {{ (string)($categoryId ?? '') === (string)$category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
                @endforeach
            </select>
            <select name="creator_id" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                <option value="">All Creators</option>
                @foreach($creators ?? [] as $creator)
                <option value="{{ $creator->id }}" {{ (string)($creatorId ?? '') === (string)$creator->id ? 'selected' : '' }}>
                    {{ $creator->name }}
                </option>
                @endforeach
            </select>
            <select name="premium" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                <option value="">All Types</option>
                <option value="premium" {{ ($premium ?? '') === 'premium' ? 'selected' : '' }}>Premium</option>
                <option value="free" {{ ($premium ?? '') === 'free' ? 'selected' : '' }}>Free</option>
            </select>
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
<script>
(() => {
    const inputs = document.querySelectorAll('.js-suggest-input');
    if (!inputs.length) return;
    inputs.forEach((input) => {
        const list = input.parentElement.querySelector('.js-suggest-list');
        if (!list) return;
        let timer = null;
        let controller = null;
        const hideList = () => { list.classList.add('hidden'); list.innerHTML = ''; };
        const showList = () => list.classList.remove('hidden');
        const fetchSuggestions = (q) => {
            const type = input.dataset.suggestType || 'home';
            if (controller) controller.abort();
            controller = new AbortController();
            fetch(`/admin/search-suggestions?type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`, {
                signal: controller.signal
            })
                .then((res) => res.json())
                .then((data) => {
                    const items = data.items || [];
                    if (!items.length) return hideList();
                    list.innerHTML = items.map((item) => (
                        `<div class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer" data-value="${item.value}">${item.label}</div>`
                    )).join('');
                    showList();
                })
                .catch(() => {});
        };
        input.addEventListener('input', (e) => {
            const q = e.target.value.trim();
            if (q.length < 2) return hideList();
            clearTimeout(timer);
            timer = setTimeout(() => fetchSuggestions(q), 250);
        });
        list.addEventListener('click', (e) => {
            const target = e.target;
            if (!target.dataset.value) return;
            input.value = target.dataset.value;
            hideList();
            const form = input.closest('form');
            if (form) form.submit();
        });
        document.addEventListener('click', (e) => {
            if (e.target === input || list.contains(e.target)) return;
            hideList();
        });
    });
})();
</script>
@endsection