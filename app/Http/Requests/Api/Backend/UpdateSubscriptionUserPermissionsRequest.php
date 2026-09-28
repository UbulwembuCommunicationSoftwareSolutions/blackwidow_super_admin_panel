<?php

namespace App\Http\Requests\Api\Backend;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array', 'max:5000'],
            'permissions.*' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.present' => 'Send the full list of permissions to grant, even when it is empty.',
            'permissions.array' => 'Permissions must be a list of permission names.',
            'permissions.*.string' => 'Each permission must be a permission name.',
            'permissions.*.distinct' => 'A permission may only be listed once.',
        ];
    }
}
