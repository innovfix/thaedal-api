<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_tamil' => $this->name_tamil,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'color' => $this->color,
            'videos_count' => $this->videos_count ?? $this->videos()->published()->count(),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}

