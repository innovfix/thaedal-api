<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Models\VideoInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    /**
     * Get all videos with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $sortBy = $request->input('sort_by', 'published_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $videos = Video::published()
            ->with(['category', 'creator'])
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);

        return $this->paginated(
            $videos->through(fn($video) => new VideoResource($video))
        );
    }

    /**
     * Get top/popular videos
     */
    public function top(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);

        $videos = Video::published()
            ->with(['category', 'creator'])
            ->popular()
            ->limit($limit)
            ->get();

        return $this->success(VideoResource::collection($videos));
    }

    /**
     * Get new releases
     */
    public function newReleases(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);

        $videos = Video::published()
            ->with(['category', 'creator'])
            ->recent()
            ->paginate($perPage);

        return $this->paginated(
            $videos->through(fn($video) => new VideoResource($video))
        );
    }

    /**
     * Get today's videos
     */
    public function today(Request $request): JsonResponse
    {
        $videos = Video::published()
            ->with(['category', 'creator'])
            ->today()
            ->recent()
            ->get();

        return $this->success(VideoResource::collection($videos));
    }

    /**
     * Get single video by ID
     */
    public function show(string $id): JsonResponse
    {
        $video = Video::with(['category', 'creator'])
            ->published()
            ->find($id);

        if (!$video) {
            return $this->notFound('Video not found');
        }

        return $this->success(new VideoResource($video));
    }

    /**
     * Get videos by category
     */
    public function byCategory(Request $request, string $categoryId): JsonResponse
    {
        $perPage = $request->input('per_page', 20);

        $videos = Video::published()
            ->where('category_id', $categoryId)
            ->with(['category', 'creator'])
            ->recent()
            ->paginate($perPage);

        return $this->paginated(
            $videos->through(fn($video) => new VideoResource($video))
        );
    }

    /**
     * Search videos
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'category' => 'nullable|uuid|exists:categories,id',
        ]);

        $perPage = $request->input('per_page', 20);

        $query = Video::published()
            ->with(['category', 'creator'])
            ->search($request->q);

        if ($request->category) {
            $query->where('category_id', $request->category);
        }

        $videos = $query->recent()->paginate($perPage);

        return $this->paginated(
            $videos->through(fn($video) => new VideoResource($video))
        );
    }

    /**
     * Like a video
     */
    public function like(Request $request, string $id): JsonResponse
    {
        $video = Video::published()->find($id);

        if (!$video) {
            return $this->notFound('Video not found');
        }

        $user = $request->user();

        // Remove dislike if exists
        VideoInteraction::where([
            'user_id' => $user->id,
            'video_id' => $id,
            'type' => 'dislike',
        ])->delete();

        // Add like
        $interaction = VideoInteraction::firstOrCreate([
            'user_id' => $user->id,
            'video_id' => $id,
            'type' => 'like',
        ]);

        // Update counts
        $video->update([
            'likes_count' => $video->interactions()->likes()->count(),
            'dislikes_count' => $video->interactions()->dislikes()->count(),
        ]);

        return $this->success([
            'likes_count' => $video->likes_count,
            'dislikes_count' => $video->dislikes_count,
            'is_liked' => true,
            'is_disliked' => false,
        ]);
    }

    /**
     * Unlike a video
     */
    public function unlike(Request $request, string $id): JsonResponse
    {
        $video = Video::published()->find($id);

        if (!$video) {
            return $this->notFound('Video not found');
        }

        VideoInteraction::where([
            'user_id' => $request->user()->id,
            'video_id' => $id,
            'type' => 'like',
        ])->delete();

        $video->update([
            'likes_count' => $video->interactions()->likes()->count(),
        ]);

        return $this->success([
            'likes_count' => $video->likes_count,
            'dislikes_count' => $video->dislikes_count,
            'is_liked' => false,
            'is_disliked' => $video->isDislikedBy($request->user()),
        ]);
    }

    /**
     * Dislike a video
     */
    public function dislike(Request $request, string $id): JsonResponse
    {
        $video = Video::published()->find($id);

        if (!$video) {
            return $this->notFound('Video not found');
        }

        $user = $request->user();

        // Remove like if exists
        VideoInteraction::where([
            'user_id' => $user->id,
            'video_id' => $id,
            'type' => 'like',
        ])->delete();

        // Add dislike
        VideoInteraction::firstOrCreate([
            'user_id' => $user->id,
            'video_id' => $id,
            'type' => 'dislike',
        ]);

        // Update counts
        $video->update([
            'likes_count' => $video->interactions()->likes()->count(),
            'dislikes_count' => $video->interactions()->dislikes()->count(),
        ]);

        return $this->success([
            'likes_count' => $video->likes_count,
            'dislikes_count' => $video->dislikes_count,
            'is_liked' => false,
            'is_disliked' => true,
        ]);
    }

    /**
     * Remove dislike from video
     */
    public function removeDislike(Request $request, string $id): JsonResponse
    {
        $video = Video::published()->find($id);

        if (!$video) {
            return $this->notFound('Video not found');
        }

        VideoInteraction::where([
            'user_id' => $request->user()->id,
            'video_id' => $id,
            'type' => 'dislike',
        ])->delete();

        $video->update([
            'dislikes_count' => $video->interactions()->dislikes()->count(),
        ]);

        return $this->success([
            'likes_count' => $video->likes_count,
            'dislikes_count' => $video->dislikes_count,
            'is_liked' => $video->isLikedBy($request->user()),
            'is_disliked' => false,
        ]);
    }

    /**
     * Save a video
     */
    public function save(Request $request, string $id): JsonResponse
    {
        $video = Video::published()->find($id);

        if (!$video) {
            return $this->notFound('Video not found');
        }

        VideoInteraction::firstOrCreate([
            'user_id' => $request->user()->id,
            'video_id' => $id,
            'type' => 'save',
        ]);

        $video->update([
            'saves_count' => $video->interactions()->saves()->count(),
        ]);

        return $this->success(null, 'Video saved');
    }

    /**
     * Unsave a video
     */
    public function unsave(Request $request, string $id): JsonResponse
    {
        $video = Video::published()->find($id);

        if (!$video) {
            return $this->notFound('Video not found');
        }

        VideoInteraction::where([
            'user_id' => $request->user()->id,
            'video_id' => $id,
            'type' => 'save',
        ])->delete();

        $video->update([
            'saves_count' => $video->interactions()->saves()->count(),
        ]);

        return $this->success(null, 'Video removed from saved');
    }
}

