<?php

namespace App\Http\Requests\Api\V1;

/**
 * For operations that act on an existing user and need only to identify it.
 */
class UserSyncLocateRequest extends TenantSyncRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'app_url' => 'required|string',
            'origin' => 'nullable|string|in:cms,firearm,responder,super_admin,lms',
            'user' => 'required|array',
            'user.super_admin_user_id' => 'nullable|integer|required_without_all:user.cms_user_id,user.email',
            'user.cms_user_id' => 'nullable|integer|required_without_all:user.super_admin_user_id,user.email',
            'user.email' => 'nullable|email|required_without_all:user.super_admin_user_id,user.cms_user_id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user.super_admin_user_id.required_without_all' => 'Identify the user by super_admin_user_id, cms_user_id or email.',
            'user.cms_user_id.required_without_all' => 'Identify the user by super_admin_user_id, cms_user_id or email.',
            'user.email.required_without_all' => 'Identify the user by super_admin_user_id, cms_user_id or email.',
        ];
    }
}
