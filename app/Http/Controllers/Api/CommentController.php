<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Get comments for a video
     */
    public function index(Request $request, string $videoId): JsonResponse
    {
        $video = Video::published()->find($videoId);

        if (!$video) {
            return $this->notFound('Video not found');
        }

        $perPage = $request->input('per_page', 20);

        $comments = $video->comments()
            ->approved()
            ->topLevel()
            ->with(['user', 'replies.user'])
            ->withCount('replies')
            ->orderByDesc('is_pinned')
            ->recent()
            ->paginate($perPage);

        return $this->paginated(
            $comments->setCollection(
                CommentResource::collection($comments->getCollection())->collect()
            )
        );
    }

    /**
     * Add a comment to a video
     */
    public function store(Request $request, string $videoId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|uuid|exists:comments,id',
        ]);

        $video = Video::published()->find($videoId);

        if (!$video) {
            return $this->notFound('Video not found');
        }

        // If replying, verify parent belongs to same video
        if ($request->parent_id) {
            $parent = Comment::where('video_id', $videoId)->find($request->parent_id);
            if (!$parent) {
                return $this->error('Invalid parent comment', 422);
            }
        }

        $comment = Comment::create([
            'video_id' => $videoId,
            'user_id' => $request->user()->id,
            'parent_id' => $request->parent_id,
            'content' => $request->content,
            'is_approved' => true, // Auto-approve for now
        ]);

        // Update counts
        $video->update([
            'comments_count' => $video->comments()->approved()->count(),
        ]);

        if ($request->parent_id) {
            Comment::find($request->parent_id)->increment('replies_count');
        }

        $comment->load('user');

        return $this->created(new CommentResource($comment), 'Comment added');
    }
}

