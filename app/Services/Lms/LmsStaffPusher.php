<?php

namespace App\Services\Lms;

use App\Models\User;
use App\Support\CustomerSync\LmsHub;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Super Admin staff users are the shared LMS's admin users. This keeps the
 * LMS copy in step: name, email and the bcrypt password hash (never
 * cleartext). Roles are managed in the LMS.
 */
class LmsStaffPusher
{
    public function enabled(): bool
    {
        return config('user_sync.enabled', true)
            && LmsHub::configuredUrl() !== null
            && filled(config('services.lms.sync_token'));
    }

    /**
     * @return array<string, mixed>|null The LMS response, or null when the LMS is not configured.
     */
    public function upsert(User $user): ?array
    {
        return $this->post('staff', [
            'super_admin_staff_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'password_hash' => $user->getAuthPassword(),
        ], $user->id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function archive(int $staffId): ?array
    {
        return $this->post('staff/archive', ['super_admin_staff_id' => $staffId], $staffId, allowMissing: true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function post(string $path, array $payload, int $staffId, bool $allowMissing = false): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $response = Http::withToken((string) config('services.lms.sync_token'))
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->connectTimeout(10)
            ->post(LmsHub::configuredUrl().'/admin-api/v1/sync/'.$path, $payload);

        if ($allowMissing && $response->notFound()) {
            return $response->json() ?? [];
        }

        if (! $response->successful()) {
            Log::warning('LMS staff push failed', [
                'staff_user_id' => $staffId,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("LMS staff push to {$path} failed with HTTP {$response->status()}");
        }

        return $response->json() ?? [];
    }
}
