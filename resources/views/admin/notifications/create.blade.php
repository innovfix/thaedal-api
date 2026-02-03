@extends('admin.layouts.app')

@section('title', 'Send Notification')
@section('page_title', 'Send Notification')

@section('content')
@if(!$configured)
    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
        OneSignal is not configured. Please go to <a class="underline" href="{{ route('admin.notifications.settings') }}">Settings</a>.
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Form -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Compose Notification</h2>
        
        <form method="POST" action="{{ route('admin.notifications.send') }}" class="space-y-5" id="notificationForm">
            @csrf

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Title <span class="text-gray-400 font-normal" id="titleCount">(0/80)</span>
                </label>
                <input type="text" name="title" id="titleInput" value="{{ old('title') }}" maxlength="80"
                       placeholder="🎉 New video available!"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- Quick Emojis -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Quick Emojis</label>
                <div class="flex flex-wrap gap-2" id="emojiPicker">
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🎉">🎉</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🔥">🔥</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="⭐">⭐</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🎬">🎬</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="📺">📺</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🎁">🎁</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="💎">💎</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🚀">🚀</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="❤️">❤️</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="✨">✨</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🎯">🎯</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="📢">📢</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🔔">🔔</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="💰">💰</button>
                    <button type="button" class="emoji-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xl" data-emoji="🏆">🏆</button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Click to insert emoji at cursor position</p>
            </div>

            <!-- Message -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Message <span class="text-gray-400 font-normal" id="messageCount">(0/240)</span>
                </label>
                <textarea name="message" id="messageInput" rows="3" maxlength="240"
                          placeholder="Check out our latest premium content..."
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">{{ old('message') }}</textarea>
                @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- Audience -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Send To (User Category)</label>
                <div class="grid grid-cols-2 gap-3">
                    <!-- All Users -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="all" class="peer sr-only" {{ old('audience', 'all') === 'all' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-gold peer-checked:bg-gold/10 transition-all">
                            <div class="text-2xl mb-1">👥</div>
                            <div class="text-sm font-medium">All Users</div>
                            <div class="text-xs text-gray-500">Everyone</div>
                        </div>
                    </label>
                    
                    <!-- Cat1: All Free Users -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="cat1" class="peer sr-only" {{ old('audience') === 'cat1' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all">
                            <div class="text-2xl mb-1">🆕</div>
                            <div class="text-sm font-medium">Cat1: All Free Users</div>
                            <div class="text-xs text-gray-500">Not subscribed</div>
                        </div>
                    </label>
                    
                    <!-- Cat2: Premium (Autopay On) -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="cat2" class="peer sr-only" {{ old('audience') === 'cat2' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-purple-500 peer-checked:bg-purple-50 transition-all">
                            <div class="text-2xl mb-1">💎</div>
                            <div class="text-sm font-medium">Cat2: Premium</div>
                            <div class="text-xs text-gray-500">Autopay ON</div>
                        </div>
                    </label>
                    
                    <!-- Cat3: Lapsed (Autopay Off) -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="cat3" class="peer sr-only" {{ old('audience') === 'cat3' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-orange-500 peer-checked:bg-orange-50 transition-all">
                            <div class="text-2xl mb-1">⏸️</div>
                            <div class="text-sm font-medium">Cat3: Lapsed</div>
                            <div class="text-xs text-gray-500">Paid ₹2, autopay OFF</div>
                        </div>
                    </label>
                    
                    <!-- All Subscribed -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="premium" class="peer sr-only" {{ old('audience') === 'premium' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all">
                            <div class="text-2xl mb-1">✅</div>
                            <div class="text-sm font-medium">All Subscribed</div>
                            <div class="text-xs text-gray-500">Any active sub</div>
                        </div>
                    </label>

                    <!-- Specific User -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="audience" value="user" class="peer sr-only" {{ old('audience') === 'user' ? 'checked' : '' }}>
                        <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-gray-700 peer-checked:bg-gray-50 transition-all">
                            <div class="text-2xl mb-1">🎯</div>
                            <div class="text-sm font-medium">Specific User</div>
                            <div class="text-xs text-gray-500">By User ID</div>
                        </div>
                    </label>
                    
                </div>
                @error('audience')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- Specific User ID -->
            <div id="userIdWrapper" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">User ID</label>
                <input type="text" name="user_id" id="userIdInput" value="{{ old('user_id') }}"
                       placeholder="e.g. a0f174d0-0c03-4330-b602-20abd17e5471"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                <p class="text-xs text-gray-500 mt-1">Paste the user's ID from Admin Users page.</p>
                @error('user_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- URL (optional) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Deep Link URL <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="text" name="url" id="urlInput" value="{{ old('url') }}"
                       placeholder="https://thedal.innovfix.ai/..."
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                @error('url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- Image URL (optional) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Image URL <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="url" name="big_picture" id="bigPictureInput" value="{{ old('big_picture') }}"
                       placeholder="https://example.com/image.jpg"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                <p class="text-xs text-gray-500 mt-1">Big picture shown in notification (Android)</p>
                @error('big_picture')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2 gradient-gold text-navy font-semibold rounded-lg hover:opacity-90 disabled:opacity-50" {{ !$configured ? 'disabled' : '' }}>
                    🚀 Send Notification
                </button>
                <a href="{{ route('admin.notifications.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">← Back</a>
            </div>
        </form>
    </div>

    <!-- Preview & Info -->
    <div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">📱 Preview</h2>
            
            <!-- Android Notification Preview -->
            <div class="bg-gray-100 rounded-xl p-4">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <!-- Notification Header -->
                    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100">
                        <div class="w-5 h-5 bg-gradient-to-br from-amber-400 to-amber-600 rounded flex items-center justify-center">
                            <span class="text-white text-xs font-bold">த</span>
                        </div>
                        <span class="text-xs text-gray-600">thedal • now</span>
                    </div>
                    
                    <!-- Notification Content -->
                    <div class="px-3 py-2">
                        <div id="previewTitle" class="font-semibold text-gray-900 text-sm">Notification Title</div>
                        <div id="previewMessage" class="text-gray-600 text-sm mt-0.5 line-clamp-2">Your message will appear here...</div>
                    </div>
                    
                    <!-- Big Picture Preview -->
                    <div id="previewImageWrapper" class="hidden px-3 pb-2">
                        <img id="previewImage" src="" alt="Preview" class="w-full h-32 object-cover rounded-lg">
                    </div>
                </div>
            </div>
            
            <!-- Audience Badge -->
            <div class="mt-4 flex items-center gap-2">
                <span class="text-sm text-gray-600">Sending to:</span>
                <span id="previewAudience" class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">All Users</span>
            </div>
        </div>

        <!-- Category Explanation -->
        <div class="bg-white rounded-lg shadow p-6 mt-4">
            <h3 class="font-bold text-gray-800 mb-3">📊 User Categories Explained</h3>
            <div class="space-y-3 text-sm">
                <div class="flex items-start gap-3 p-2 bg-green-50 rounded">
                    <span class="text-xl">🆕</span>
                    <div>
                        <div class="font-semibold text-green-800">Cat1: All Free Users</div>
                        <div class="text-green-700">Users who have not subscribed yet. Great for "Try Premium!" promos.</div>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-2 bg-purple-50 rounded">
                    <span class="text-xl">💎</span>
                    <div>
                        <div class="font-semibold text-purple-800">Cat2: Premium (Autopay On)</div>
                        <div class="text-purple-700">Active subscribers with autopay enabled. Send new content alerts!</div>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-2 bg-orange-50 rounded">
                    <span class="text-xl">⏸️</span>
                    <div>
                        <div class="font-semibold text-orange-800">Cat3: Lapsed (Autopay Off)</div>
                        <div class="text-orange-700">Paid ₹2 but turned off autopay. Win them back with "Re-enable autopay" messages!</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tips -->
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-4">
            <h3 class="font-semibold text-amber-800 mb-2">💡 Notification Tips</h3>
            <ul class="text-sm text-amber-700 space-y-1">
                <li>• <b>Cat1:</b> "Start your 7-day free trial today! 🎬"</li>
                <li>• <b>Cat2:</b> "New premium video just dropped! 🔥"</li>
                <li>• <b>Cat3:</b> "We miss you! Re-enable autopay for ₹99/mo 💎"</li>
                <li>• Best times: 9-11 AM, 7-9 PM</li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('titleInput');
    const messageInput = document.getElementById('messageInput');
    const bigPictureInput = document.getElementById('bigPictureInput');
    const titleCount = document.getElementById('titleCount');
    const messageCount = document.getElementById('messageCount');
    const previewTitle = document.getElementById('previewTitle');
    const previewMessage = document.getElementById('previewMessage');
    const previewAudience = document.getElementById('previewAudience');
    const previewImage = document.getElementById('previewImage');
    const previewImageWrapper = document.getElementById('previewImageWrapper');
    const audienceRadios = document.querySelectorAll('input[name="audience"]');
        const emojiButtons = document.querySelectorAll('.emoji-btn');
        const userIdWrapper = document.getElementById('userIdWrapper');
        const userIdInput = document.getElementById('userIdInput');

    // Track last focused input for emoji insertion
    let lastFocusedInput = titleInput;
    
    titleInput.addEventListener('focus', () => lastFocusedInput = titleInput);
    messageInput.addEventListener('focus', () => lastFocusedInput = messageInput);

    // Emoji insertion
    emojiButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const emoji = this.dataset.emoji;
            const input = lastFocusedInput;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const text = input.value;
            input.value = text.substring(0, start) + emoji + text.substring(end);
            input.selectionStart = input.selectionEnd = start + emoji.length;
            input.focus();
            updatePreview();
            updateCounts();
        });
    });

    // Character counters
    function updateCounts() {
        titleCount.textContent = `(${titleInput.value.length}/80)`;
        messageCount.textContent = `(${messageInput.value.length}/240)`;
    }

    // Preview update
    function updatePreview() {
        previewTitle.textContent = titleInput.value || 'Notification Title';
        previewMessage.textContent = messageInput.value || 'Your message will appear here...';
        
        // Image preview
        const imageUrl = bigPictureInput.value.trim();
        if (imageUrl) {
            previewImage.src = imageUrl;
            previewImageWrapper.classList.remove('hidden');
        } else {
            previewImageWrapper.classList.add('hidden');
        }
    }

    // Audience preview
    function updateAudiencePreview() {
        const checked = document.querySelector('input[name="audience"]:checked');
        const labels = {
            all: '👥 All Users',
            cat1: '🆕 Cat1: All Free Users',
            cat2: '💎 Cat2: Premium',
            cat3: '⏸️ Cat3: Lapsed',
            premium: '✅ All Subscribed',
            user: '🎯 Specific User'
        };
        const colors = {
            all: 'bg-blue-100 text-blue-800',
            cat1: 'bg-green-100 text-green-800',
            cat2: 'bg-purple-100 text-purple-800',
            cat3: 'bg-orange-100 text-orange-800',
            premium: 'bg-indigo-100 text-indigo-800',
            user: 'bg-gray-100 text-gray-800'
        };
        previewAudience.textContent = labels[checked.value] || 'All Users';
        previewAudience.className = `px-2 py-1 text-xs font-medium rounded-full ${colors[checked.value] || colors.all}`;
    }

    function updateUserIdVisibility() {
        const checked = document.querySelector('input[name="audience"]:checked');
        const isUser = checked && checked.value === 'user';
        userIdWrapper.classList.toggle('hidden', !isUser);
        if (isUser) {
            userIdInput.setAttribute('required', 'required');
        } else {
            userIdInput.removeAttribute('required');
        }
    }

    // Event listeners
    titleInput.addEventListener('input', () => { updateCounts(); updatePreview(); });
    messageInput.addEventListener('input', () => { updateCounts(); updatePreview(); });
    bigPictureInput.addEventListener('input', updatePreview);
    audienceRadios.forEach(radio => radio.addEventListener('change', () => {
        updateAudiencePreview();
        updateUserIdVisibility();
    }));

    // Initial
    updateCounts();
    updatePreview();
    updateAudiencePreview();
    updateUserIdVisibility();
});
</script>
@endsection