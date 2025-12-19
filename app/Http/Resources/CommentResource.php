<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'video_id' => $this->video_id,
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name ?? 'User',
                'avatar_url' => $this->user->avatar_url,
            ],
            'content' => $this->content,
            'likes_count' => $this->likes_count,
            'replies_count' => $this->replies_count ?? 0,
            'is_pinned' => $this->is_pinned,
            'is_liked' => false, // TODO: Implement comment likes
            'replies' => $this->whenLoaded('replies', function () {
                return CommentResource::collection($this->replies);
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

