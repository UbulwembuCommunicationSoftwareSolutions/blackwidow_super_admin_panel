<?php

namespace App\Http\Requests\Api\V1;

/**
 * Passwords cross the boundary as cleartext exactly once, here, and each side
 * hashes with its own driver. Hashes are never copied between systems.
 */
class UserSyncPasswordRequest extends UserSyncLocateRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'password' => 'required|string|min:8',
        ]);
    }

    public function cleartextPassword(): string
    {
        return (string) $this->validated('password');
    }
}
