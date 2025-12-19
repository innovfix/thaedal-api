<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchHistory extends Model
{
    use HasFactory;

    protected $table = 'watch_history';

    protected $fillable = [
        'user_id',
        'video_id',
        'watched_duration',
        'progress_percentage',
        'completed',
        'last_watched_at',
    ];

    protected function casts(): array
    {
        return [
            'watched_duration' => 'integer',
            'progress_percentage' => 'integer',
            'completed' => 'boolean',
            'last_watched_at' => 'datetime',
        ];
    }

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
    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    public function scopeInProgress($query)
    {
        return $query->where('completed', false)
            ->where('progress_percentage', '>', 0);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('last_watched_at');
    }

    // Helpers
    public function updateProgress(int $duration, int $totalDuration): void
    {
        $this->watched_duration = $duration;
        $this->progress_percentage = $totalDuration > 0 
            ? min(100, (int) (($duration / $totalDuration) * 100))
            : 0;
        $this->completed = $this->progress_percentage >= 90;
        $this->last_watched_at = now();
        $this->save();
    }
}

