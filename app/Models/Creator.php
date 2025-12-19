<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Creator extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'bio',
        'avatar_url',
        'cover_url',
        'subscribers_count',
        'total_views',
        'is_verified',
        'is_active',
        'social_links',
    ];

    protected function casts(): array
    {
        return [
            'subscribers_count' => 'integer',
            'total_views' => 'integer',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'social_links' => 'array',
        ];
    }

    // Relationships
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopePopular($query)
    {
        return $query->orderByDesc('subscribers_count');
    }

    // Helpers
    public function incrementViews(int $count = 1): void
    {
        $this->increment('total_views', $count);
    }

    public function getVideosCountAttribute(): int
    {
        return $this->videos()->published()->count();
    }
}

