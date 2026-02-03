<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Creator;
use App\Models\HomeTopic;
use App\Models\Category;
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
        $categoryId = $request->get('category_id');
        $creatorId = $request->get('creator_id');
        $premium = $request->get('premium');

        $videos = Video::query()
            ->with(['category', 'creator'])
            ->when($q, function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%");
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($creatorId, function ($query) use ($creatorId) {
                $query->where('creator_id', $creatorId);
            })
            ->when($premium === 'premium', function ($query) {
                $query->where('is_premium', true);
            })
            ->when($premium === 'free', function ($query) {
                $query->where('is_premium', false);
            })
            ->latest('published_at')
            ->limit(25)
            ->get();

        $categories = Category::query()->orderBy('name')->get();
        $creators = Creator::query()->orderBy('name')->get();

        return view('admin.home.index', compact('featured', 'topics', 'videos', 'q', 'categories', 'creators', 'categoryId', 'creatorId', 'premium'));
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