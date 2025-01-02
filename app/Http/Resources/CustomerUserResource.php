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
            'cellphone' => $this->cellphone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'customer_id' => $this->customer_id,
            'console_access' => $this->console_access,
            'firearm_access' => $this->firearm_access,
            'responder_access' => $this->responder_access,
            'reporter_access'   => $this->reporter_access,
            'security_access'   => $this->security_access,
            'driver_access'     => $this->driver_access,
            'survey_access'     => $this->survey_access,
            'time_and_attendance_access' => $this->time_and_attendance_access,
            'stock_access'      => $this->stock_access,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
