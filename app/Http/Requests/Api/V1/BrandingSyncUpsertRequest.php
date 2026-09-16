<?php

namespace App\Http\Requests\Api\V1;

use App\Support\BrandingSync\BrandingSyncPayload;

class BrandingSyncUpsertRequest extends TenantSyncRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'app_url' => ['required', 'string'],
            'origin' => ['sometimes', 'string', 'in:cms,super_admin,firearm,responder'],
            'branding' => ['required', 'array'],
        ], BrandingSyncPayload::validationRules('branding'));
    }

    public function brandingPayload(): BrandingSyncPayload
    {
        return BrandingSyncPayload::fromArray((array) $this->validated('branding', []));
    }
}
