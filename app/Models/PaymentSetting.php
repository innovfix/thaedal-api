<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = [
        'verification_fee_amount_paise',
        'autopay_amount_paise',
        'paywall_video_type',
        'paywall_video_url',
        'paywall_video_path',
        'paywall_video_view_count',
        'pricing_version',
        'pricing_updated_at',
    ];

    protected $casts = [
        'verification_fee_amount_paise' => 'integer',
        'autopay_amount_paise' => 'integer',
        'paywall_video_view_count' => 'integer',
        'pricing_version' => 'integer',
        'pricing_updated_at' => 'datetime',
    ];

    public static function current(): self
    {
        $settings = static::query()->orderByDesc('created_at')->first();
        if ($settings) {
            return $settings;
        }

        return static::query()->create([
            'verification_fee_amount_paise' => 200,
            'autopay_amount_paise' => 9900,
            'paywall_video_type' => 'url',
            'paywall_video_url' => null,
            'paywall_video_path' => null,
            'paywall_video_view_count' => 0,
            'pricing_version' => 1,
            'pricing_updated_at' => now(),
        ]);
    }

    public function paywallVideoUrl(): ?string
    {
        if (($this->paywall_video_type ?? 'url') === 'url') {
            return $this->paywall_video_url;
        }
        
        if ($this->paywall_video_path) {
            return url('storage/' . $this->paywall_video_path);
        }
        
        return null;
    }
}
