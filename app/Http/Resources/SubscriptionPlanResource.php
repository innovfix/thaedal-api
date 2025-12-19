<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_tamil' => $this->name_tamil,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'formatted_price' => $this->formatted_price,
            'duration_days' => $this->duration_days,
            'duration_type' => $this->duration_type,
            'duration_label' => $this->duration_label,
            'trial_days' => $this->trial_days,
            'features' => $this->features ?? [],
            'is_popular' => $this->is_popular,
            'discount_percentage' => $this->discount_percentage,
            'original_price' => $this->original_price ? (float) $this->original_price : null,
            'formatted_original_price' => $this->formatted_original_price,
            'has_discount' => $this->hasDiscount(),
        ];
    }
}

