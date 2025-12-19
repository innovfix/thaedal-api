<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'name_tamil',
        'description',
        'price',
        'currency',
        'duration_days',
        'duration_type',
        'trial_days',
        'features',
        'is_popular',
        'discount_percentage',
        'original_price',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'duration_days' => 'integer',
            'trial_days' => 'integer',
            'features' => 'array',
            'is_popular' => 'boolean',
            'discount_percentage' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Relationships
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    // Helpers
    public function hasDiscount(): bool
    {
        return $this->discount_percentage !== null && $this->discount_percentage > 0;
    }

    public function getFormattedPriceAttribute(): string
    {
        return '₹' . number_format($this->price, 0);
    }

    public function getFormattedOriginalPriceAttribute(): ?string
    {
        return $this->original_price 
            ? '₹' . number_format($this->original_price, 0)
            : null;
    }

    public function getDurationLabelAttribute(): string
    {
        return match($this->duration_type) {
            'monthly' => 'per month',
            'quarterly' => 'per 3 months',
            'yearly' => 'per year',
            'lifetime' => 'lifetime',
            default => "{$this->duration_days} days",
        };
    }
}

