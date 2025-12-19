<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'cover_url' => $this->cover_url,
            'videos_count' => $this->videos_count ?? $this->videos()->published()->count(),
            'subscribers_count' => $this->subscribers_count,
            'total_views' => $this->total_views,
            'is_verified' => $this->is_verified,
            'social_links' => $this->social_links,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

