<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomerSubscription */
class CustomerSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'id' => $this->id,
            'app_url' => $this->app_url,
            'console_login_logo' => $this->console_login_logo,
            'console_menu_logo' => $this->console_menu_logo,
            'console_background_logo' => $this->console_background_logo,
            'app_install_logo' => $this->app_install_logo,
            'app_background_logo' => $this->app_background_logo,

            'customer_id' => $this->customer_id,
            'subscription_type_id' => $this->subscription_type_id,

            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'subscriptionType' => new SubscriptionTypeResource($this->whenLoaded('subscriptionType')),
        ];
    }
}
