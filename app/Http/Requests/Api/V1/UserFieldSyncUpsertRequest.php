<?php

namespace App\Http\Requests\Api\V1;

use App\Support\UserFieldSync\UserFieldSyncPayload;

class UserFieldSyncUpsertRequest extends TenantSyncRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'app_url' => ['required', 'string'],
            'origin' => ['sometimes', 'string', 'in:cms,super_admin,firearm,responder,lms'],
            'user_field' => ['required', 'array'],
        ], UserFieldSyncPayload::validationRules('user_field'));
    }

    public function userFieldPayload(): UserFieldSyncPayload
    {
        return UserFieldSyncPayload::fromArray((array) $this->validated('user_field', []));
    }

    public function superAdminCustomerId(): ?int
    {
        $fromField = $this->validated('user_field.super_admin_customer_id')
            ?? $this->input('user_field.super_admin_customer_id');

        return is_numeric($fromField) ? (int) $fromField : null;
    }
}
