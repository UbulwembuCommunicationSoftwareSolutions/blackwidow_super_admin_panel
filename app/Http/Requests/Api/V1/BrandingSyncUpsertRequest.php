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
            'origin' => ['sometimes', 'string', 'in:cms,super_admin,firearm,responder,lms'],
            'branding' => ['required', 'array'],
            'branding.super_admin_customer_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ], BrandingSyncPayload::validationRules('branding'));
    }

    public function brandingPayload(): BrandingSyncPayload
    {
        return BrandingSyncPayload::fromArray((array) $this->validated('branding', []));
    }

    public function superAdminCustomerId(): ?int
    {
        $fromBranding = $this->validated('branding.super_admin_customer_id')
            ?? $this->input('branding.super_admin_customer_id');

        return is_numeric($fromBranding) ? (int) $fromBranding : null;
    }
}
