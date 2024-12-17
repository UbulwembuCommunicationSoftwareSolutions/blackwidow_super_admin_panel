<?php

namespace App\Http\Resources;

use App\Models\DeploymentTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeploymentTemplate */
class DeploymentTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'script' => $this->script,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'subscription_type_id' => $this->subscription_type_id,

            'subscriptionType' => new SubscriptionTypeResource($this->whenLoaded('subscriptionType')),
        ];
    }
}
