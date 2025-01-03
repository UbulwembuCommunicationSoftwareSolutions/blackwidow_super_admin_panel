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
            'level_one_description' => $this->level_one_description,
            'level_one_in_use' => $this->level_one_in_use,
            'level_two_description' => $this->level_two_description,
            'level_two_in_use' => $this->level_two_in_use,
            'level_three_description' => $this->level_three_description,
            'level_three_in_use' => $this->level_three_in_use,
            'level_four_description' => $this->level_four_description,
            'level_five_description' => $this->level_five_description,
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'cellphone' => $this->cellphone,
        ];
    }
}
