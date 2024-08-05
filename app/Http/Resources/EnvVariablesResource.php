<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EnvVariables */
class EnvVariablesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'id' => $this->id,
            'key' => $this->key,
            'value' => $this->value,

            'customer_subscription_id' => $this->customer_subscription_id,

            'customerSubscription' => new CustomerSubscriptionResource($this->whenLoaded('customerSubscription')),
        ];
    }
}
