<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class HomeTopicVideo extends Pivot
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'home_topic_videos';

    protected $fillable = [
        'home_topic_id',
        'video_id',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $pivot) {
            if (empty($pivot->id)) {
                $pivot->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
