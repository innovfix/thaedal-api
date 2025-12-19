<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'plan' => $this->whenLoaded('plan', function () {
                return new SubscriptionPlanResource($this->plan);
            }),
            'status' => $this->status,
            'is_trial' => $this->is_trial,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'auto_renew' => $this->auto_renew,
            'payment_method_id' => $this->payment_method_id,
            'next_billing_date' => $this->next_billing_date?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'days_remaining' => $this->daysRemaining(),
            'is_active' => $this->isActive(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

