<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'is_default',
        'details',
        'razorpay_token',
        'last_four',
        'brand',
    ];

    protected $hidden = [
        'details',
        'razorpay_token',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'details' => 'encrypted:array',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeCards($query)
    {
        return $query->where('type', 'card');
    }

    public function scopeUpi($query)
    {
        return $query->where('type', 'upi');
    }

    // Helpers
    public function setAsDefault(): void
    {
        // Remove default from other methods
        $this->user->paymentMethods()
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);
        
        $this->update(['is_default' => true]);
    }

    public function getDisplayNameAttribute(): string
    {
        return match($this->type) {
            'card' => "{$this->brand} •••• {$this->last_four}",
            'upi' => $this->details['vpa'] ?? 'UPI',
            'netbanking' => $this->details['bank_name'] ?? 'Net Banking',
            'wallet' => $this->details['wallet_name'] ?? 'Wallet',
            default => 'Payment Method',
        };
    }

    public function getIconAttribute(): string
    {
        if ($this->type === 'card') {
            return match(strtolower($this->brand ?? '')) {
                'visa' => 'visa',
                'mastercard' => 'mastercard',
                'rupay' => 'rupay',
                'amex', 'american express' => 'amex',
                default => 'card',
            };
        }
        
        return $this->type;
    }
}

