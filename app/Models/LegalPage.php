<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalPage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'page_type',
        'title',
        'content',
    ];

    const TYPE_TERMS = 'terms';
    const TYPE_PRIVACY = 'privacy';
    const TYPE_REFUND = 'refund';
    const TYPE_CONTACT = 'contact';

    public function scopeByType($query, string $type)
    {
        return $query->where('page_type', $type);
    }

    public static function getByType(string $type): ?self
    {
        return self::byType($type)->first();
    }
}
