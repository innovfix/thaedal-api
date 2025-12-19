<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'is_default' => $this->is_default,
            'display_name' => $this->display_name,
            'icon' => $this->icon,
            'created_at' => $this->created_at->toIso8601String(),
        ];

        if ($this->type === 'card') {
            $data['card'] = [
                'brand' => $this->brand,
                'last_four' => $this->last_four,
                'exp_month' => $this->details['exp_month'] ?? null,
                'exp_year' => $this->details['exp_year'] ?? null,
                'holder_name' => $this->details['holder_name'] ?? null,
            ];
        } elseif ($this->type === 'upi') {
            $data['upi'] = [
                'vpa' => $this->details['vpa'] ?? null,
            ];
        }

        return $data;
    }
}

