<?php

namespace App\Http\Requests\Api\V1;

use App\Support\UserSync\UserSyncPayload;

class UserSyncUpsertRequest extends TenantSyncRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return array_merge([
            'app_url' => 'required|string',
            'origin' => 'nullable|string|in:cms,firearm,responder,super_admin,lms',
            'user' => 'required|array',
            'password' => 'nullable|string|min:8',
        ], UserSyncPayload::validationRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user.required' => 'A canonical user payload is required.',
            'user.email.required' => 'The user payload must include an email address.',
            'password.min' => 'A synced password must be at least 8 characters.',
        ];
    }

    public function cleartextPassword(): ?string
    {
        return $this->validated('password');
    }
}
