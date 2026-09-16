<?php

namespace App\Http\Requests\Api\V1;

class BrandingSyncIndexRequest extends TenantSyncRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_url' => ['required', 'string'],
        ];
    }
}
