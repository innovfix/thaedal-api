<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'video_url' => $this->video_url,
            'video_type' => $this->video_type,
            'duration' => $this->duration,
            'duration_formatted' => $this->duration_formatted,
            'views_count' => $this->views_count,
            'likes_count' => $this->likes_count,
            'dislikes_count' => $this->dislikes_count,
            'comments_count' => $this->comments_count,
            'saves_count' => $this->saves_count,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function () {
                return new CategoryResource($this->category);
            }),
            'creator_id' => $this->creator_id,
            'creator' => $this->whenLoaded('creator', function () {
                return new CreatorResource($this->creator);
            }),
            'is_premium' => $this->is_premium,
            'is_featured' => $this->is_featured,
            'is_liked' => $user ? $this->isLikedBy($user) : false,
            'is_disliked' => $user ? $this->isDislikedBy($user) : false,
            'is_saved' => $user ? $this->isSavedBy($user) : false,
            'tags' => $this->tags,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

