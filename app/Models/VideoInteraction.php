<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'video_id',
        'type',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    // Scopes
    public function scopeLikes($query)
    {
        return $query->where('type', 'like');
    }

    public function scopeDislikes($query)
    {
        return $query->where('type', 'dislike');
    }

    public function scopeSaves($query)
    {
        return $query->where('type', 'save');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForVideo($query, $videoId)
    {
        return $query->where('video_id', $videoId);
    }
}

