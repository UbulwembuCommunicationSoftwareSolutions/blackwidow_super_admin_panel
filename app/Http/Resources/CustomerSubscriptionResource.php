<?php

namespace App\Http\Resources;

use App\Models\CustomerSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerSubscription */
class CustomerSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'id' => $this->id,
            'url' => $this->url,
            'logo_1' => $this->logo_1,
            'logo_2' => $this->logo_2,
            'logo_3' => $this->logo_3,
            'logo_4' => $this->logo_4,
            'logo_5' => $this->logo_5,

            'subscription_type_id' => $this->subscription_type_id,
            'customer_id' => $this->customer_id,

            'subscriptionType' => new SubscriptionTypeResource($this->whenLoaded('subscriptionType')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
