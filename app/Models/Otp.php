<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number',
        'otp',
        'expires_at',
        'is_used',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_used' => 'boolean',
        ];
    }

    // Scopes
    public function scopeValid($query)
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', now());
    }

    public function scopeForPhone($query, string $phoneNumber)
    {
        return $query->where('phone_number', $phoneNumber);
    }

    // Helpers
    public function isValid(): bool
    {
        return !$this->is_used && $this->expires_at > now();
    }

    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    public function markAsUsed(): void
    {
        $this->update(['is_used' => true]);
    }

    public static function generate(string $phoneNumber, string $ipAddress = null): self
    {
        $otpLength = (int) config('app.otp_length', 6);
        $expiryMinutes = (int) config('app.otp_expiry_minutes', 5);
        
        // Reuse existing valid OTP if one exists (prevents "Invalid OTP" when SMS is delayed)
        $existing = static::forPhone($phoneNumber)->valid()->first();
        if ($existing) {
            // Extend expiry and return the same OTP
            $existing->update(['expires_at' => now()->addMinutes($expiryMinutes)]);
            return $existing;
        }
        
        // Invalidate old expired/used OTPs (cleanup)
        static::forPhone($phoneNumber)->where('is_used', true)->delete();
        
        return static::create([
            'phone_number' => $phoneNumber,
            'otp' => static::generateCode($otpLength),
            'expires_at' => now()->addMinutes($expiryMinutes),
            'ip_address' => $ipAddress,
        ]);
    }

    public static function verify(string $phoneNumber, string $otp): ?self
    {
        $otpRecord = static::forPhone($phoneNumber)
            ->where('otp', $otp)
            ->valid()
            ->first();
        
        if ($otpRecord) {
            $otpRecord->markAsUsed();
        }
        
        return $otpRecord;
    }

    protected static function generateCode(int $length = 6): string
    {
        // Development: Fixed OTP for easy testing
        if (config('app.env') === 'local' || config('app.debug') === true) {
            return '011011';
        }
        
        // Production: Random OTP
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;
        return (string) random_int($min, $max);
    }
}

