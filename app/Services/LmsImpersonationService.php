<?php

namespace App\Services;

use App\Models\CustomerUser;
use App\Models\User;
use App\Support\CustomerSync\LmsHub;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks the shared LMS hub to mint one-time login links: customer users land
 * in the member portal, Super Admin staff land in the LMS admin.
 */
class LmsImpersonationService
{
    /**
     * @return array{impersonate_url: string, expires_in_minutes: int}
     */
    public function issueLink(CustomerUser $user): array
    {
        $payload = [
            'super_admin_user_id' => $user->id,
        ];

        if ($user->lms_user_id !== null) {
            $payload['user_id'] = $user->lms_user_id;
        }

        $response = $this->request('/admin-api/impersonate', $payload, ['customer_user_id' => $user->id]);

        $lmsUserId = $response->json('user_id');

        if (filled($lmsUserId) && (int) $lmsUserId !== (int) $user->lms_user_id) {
            $user->forceFill(['lms_user_id' => (int) $lmsUserId])->saveQuietly();
        }

        Log::info('LMS impersonation link issued', [
            'customer_user_id' => $user->id,
            'lms_user_id' => $user->fresh()->lms_user_id,
        ]);

        return $this->linkFrom($response);
    }

    /**
     * @return array{impersonate_url: string, expires_in_minutes: int}
     */
    public function issueStaffLink(User $user): array
    {
        $response = $this->request('/admin-api/staff-impersonate', [
            'super_admin_staff_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], ['staff_user_id' => $user->id]);

        Log::info('LMS staff admin link issued', [
            'staff_user_id' => $user->id,
            'lms_user_id' => $response->json('user_id'),
        ]);

        return $this->linkFrom($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $logContext
     */
    private function request(string $path, array $payload, array $logContext): Response
    {
        $hubUrl = LmsHub::configuredUrl();
        $token = (string) config('services.lms.sync_token', '');

        if ($hubUrl === null || $token === '') {
            throw new \RuntimeException('LMS hub URL or sync token is not configured.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->connectTimeout(10)
            ->post($hubUrl.$path, $payload);

        if (! $response->successful()) {
            Log::warning('LMS impersonation link request failed', array_merge($logContext, [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]));

            throw new \RuntimeException(
                'LMS impersonation link request failed with HTTP '.$response->status()
            );
        }

        $impersonateUrl = $response->json('impersonate_url');

        if (! is_string($impersonateUrl) || $impersonateUrl === '') {
            throw new \RuntimeException('LMS did not return an impersonation link.');
        }

        return $response;
    }

    /**
     * @return array{impersonate_url: string, expires_in_minutes: int}
     */
    private function linkFrom(Response $response): array
    {
        return [
            'impersonate_url' => (string) $response->json('impersonate_url'),
            'expires_in_minutes' => (int) ($response->json('expires_in_minutes') ?: 5),
        ];
    }
}
