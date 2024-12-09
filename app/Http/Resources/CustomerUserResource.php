<?php

namespace App\Http\Resources;

use App\Models\CustomerUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerUser */
class CustomerUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email_address' => $this->email_address,
            'password' => $this->password,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'customer_id' => $this->customer_id,

            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
