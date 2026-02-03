<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $query = Video::with('category');

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('creator_name', 'like', "%{$search}%");
        }

        if ($request->has('category')) {
            $query->where('category_id', $request->get('category'));
        }

        $videos = $query->latest()->paginate(20);
        $categories = Category::all();

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
