<?php

namespace App\Http\Requests\Api\V1;

class UserSyncIndexRequest extends TenantSyncRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'app_url' => 'required|string',
        ];
    }
}
