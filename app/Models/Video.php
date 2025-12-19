<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'thumbnail_url',
        'video_url',
        'video_type',
        'duration',
        'views_count',
        'likes_count',
        'dislikes_count',
        'comments_count',
        'saves_count',
        'category_id',
        'creator_id',
        'is_premium',
        'is_published',
        'is_featured',
        'tags',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'dislikes_count' => 'integer',
            'comments_count' => 'integer',
            'saves_count' => 'integer',
            'is_premium' => 'boolean',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'tags' => 'array',
            'published_at' => 'datetime',
        ];
    }

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(VideoInteraction::class);
    }

    public function watchHistory(): HasMany
    {
        return $this->hasMany(WatchHistory::class);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    public function scopeFree($query)
    {
        return $query->where('is_premium', false);
    }

    public function scopePopular($query)
    {
        return $query->orderByDesc('views_count');
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('published_at');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('published_at', today());
    }

    public function scopeYesterday($query)
    {
        return $query->whereDate('published_at', today()->subDay());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('published_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
              ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }

    // Helpers
    public function incrementViews(): void
    {
        $this->increment('views_count');
        $this->creator?->incrementViews();
    }

    public function isLikedBy(?User $user): bool
    {
        if (!$user) return false;
        
        return $this->interactions()
            ->where('user_id', $user->id)
            ->where('type', 'like')
            ->exists();
    }

    public function isDislikedBy(?User $user): bool
    {
        if (!$user) return false;
        
        return $this->interactions()
            ->where('user_id', $user->id)
            ->where('type', 'dislike')
            ->exists();
    }

    public function isSavedBy(?User $user): bool
    {
        if (!$user) return false;
        
        return $this->interactions()
            ->where('user_id', $user->id)
            ->where('type', 'save')
            ->exists();
    }

    public function getDurationFormattedAttribute(): string
    {
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }
}

