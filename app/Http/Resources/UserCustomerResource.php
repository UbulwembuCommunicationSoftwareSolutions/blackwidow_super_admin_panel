<?php

namespace App\Http\Resources;

use App\Models\UserCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserCustomer */
class UserCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'user_id' => $this->user_id,
            'customer_id' => $this->customer_id,

            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
