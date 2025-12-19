<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;

    protected $fillable = [
        'phone_number',
        'name',
        'email',
        'avatar_url',
        'is_subscribed',
        'is_active',
        'phone_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_subscribed' => 'boolean',
            'is_active' => 'boolean',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    // Relationships
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->orWhere('status', 'trial')
            ->latest();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function watchHistory(): HasMany
    {
        return $this->hasMany(WatchHistory::class);
    }

    public function videoInteractions(): HasMany
    {
        return $this->hasMany(VideoInteraction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSubscribed($query)
    {
        return $query->where('is_subscribed', true);
    }

    // Helpers
    public function isSubscribed(): bool
    {
        return $this->is_subscribed || $this->hasActiveSubscription();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->where('ends_at', '>', now())
            ->exists();
    }

    public function likedVideos()
    {
        return $this->videoInteractions()
            ->where('type', 'like')
            ->with('video');
    }

    public function savedVideos()
    {
        return $this->videoInteractions()
            ->where('type', 'save')
            ->with('video');
    }

    public function defaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()
            ->where('is_default', true)
            ->first();
    }
}

