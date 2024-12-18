<?php

namespace App\Http\Resources;

use App\Models\ForgeServer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ForgeServer */
class ForgeServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'forge_server_id' => $this->forge_server_id,
            'id' => $this->id,
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
