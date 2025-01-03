<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'docket_description' => $this->docket_description,
            'task_description' => $this->task_description,
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'cellphone' => $this->cellphone,
        ];
    }
}
