<?php

namespace App\Http\Requests\Api\V1;

use App\Support\UserFieldSync\UserFieldValueSyncPayload;

class UserFieldValueSyncUpsertRequest extends TenantSyncRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'app_url' => ['required', 'string'],
            'origin' => ['sometimes', 'string', 'in:cms,super_admin,firearm,responder,lms'],
            'user_field_values' => ['required', 'array'],
        ], UserFieldValueSyncPayload::validationRules('user_field_values'));
    }

    public function valuesPayload(): UserFieldValueSyncPayload
    {
        return UserFieldValueSyncPayload::fromArray((array) $this->validated('user_field_values', []));
    }

    public function superAdminCustomerId(): ?int
    {
        $fromField = $this->validated('user_field_values.super_admin_customer_id')
            ?? $this->input('user_field_values.super_admin_customer_id');

        return is_numeric($fromField) ? (int) $fromField : null;
    }
}
