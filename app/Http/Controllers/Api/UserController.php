<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\VideoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Get current user profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('subscription.plan');
        
        return $this->success(new UserResource($user));
    }

    /**
     * Update user profile
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150|unique:users,email,' . $request->user()->id,
        ]);

        $user = $request->user();
        
        $user->update($request->only(['name', 'email']));

        return $this->success(new UserResource($user), 'Profile updated successfully');
    }

    /**
     * Update user avatar
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar_url) {
            $oldPath = str_replace(Storage::url(''), '', $user->avatar_url);
            Storage::delete($oldPath);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_url' => Storage::url($path)]);

        return $this->success(new UserResource($user), 'Avatar updated successfully');
    }

    /**
     * Get user's saved videos
     */
    public function savedVideos(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        
        $videos = $request->user()
            ->videoInteractions()
            ->where('type', 'save')
            ->with('video.category', 'video.creator')
            ->latest()
            ->paginate($perPage);

        $videoResources = $videos->getCollection()->map(function ($interaction) {
            return new VideoResource($interaction->video);
        });

        return $this->paginated(
            $videos->setCollection($videoResources)
        );
    }

    /**
     * Get user's watch history
     */
    public function watchHistory(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        
        $history = $request->user()
            ->watchHistory()
            ->with('video.category', 'video.creator')
            ->recent()
            ->paginate($perPage);

        $videoResources = $history->getCollection()->map(function ($item) {
            $resource = new VideoResource($item->video);
            $resource->additional([
                'watched_duration' => $item->watched_duration,
                'progress_percentage' => $item->progress_percentage,
                'completed' => $item->completed,
                'last_watched_at' => $item->last_watched_at->toIso8601String(),
            ]);
            return $resource;
        });

        return $this->paginated(
            $history->setCollection($videoResources)
        );
    }

    /**
     * Add video to watch history
     */
    public function addToHistory(Request $request): JsonResponse
    {
        $request->validate([
            'video_id' => 'required|uuid|exists:videos,id',
            'watched_duration' => 'required|integer|min:0',
            'completed' => 'boolean',
        ]);

        $user = $request->user();
        $video = \App\Models\Video::find($request->video_id);

        $history = $user->watchHistory()->updateOrCreate(
            ['video_id' => $request->video_id],
            [
                'watched_duration' => $request->watched_duration,
                'progress_percentage' => $video->duration > 0 
                    ? min(100, (int) (($request->watched_duration / $video->duration) * 100))
                    : 0,
                'completed' => $request->input('completed', false),
                'last_watched_at' => now(),
            ]
        );

        // Increment video views if first time watching
        if ($history->wasRecentlyCreated) {
            $video->incrementViews();
        }

        return $this->success(null, 'Watch history updated');
    }
}

