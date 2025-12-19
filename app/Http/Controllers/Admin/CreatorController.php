<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Creator;
use App\Models\Video;
use Illuminate\Http\Request;

class CreatorController extends Controller
{
    public function index(Request $request)
    {
        $query = Creator::withCount('videos')
            ->withSum('videos', 'views_count');

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $creators = $query->orderBy('videos_count', 'desc')->paginate(20);

        return view('admin.creators.index', compact('creators'));
    }

    public function show(Creator $creator)
    {
        $videos = $creator->videos()
            ->with('category')
            ->latest()
            ->paginate(20);

        $stats = [
            'total_videos' => $creator->videos()->count(),
            'total_views' => $creator->videos()->sum('views_count'),
            'total_likes' => $creator->videos()->sum('likes_count'),
        ];

        return view('admin.creators.show', compact('creator', 'videos', 'stats'));
    }
}
