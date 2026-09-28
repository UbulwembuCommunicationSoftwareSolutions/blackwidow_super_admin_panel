<?php

namespace App\Services;

use App\Models\CustomerUser;
use App\Support\CustomerSync\LmsHub;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks the shared LMS hub to mint a one-time admin login link for a
 * customer user who already holds an SSO cookie on the parent domain.
 */
class LmsImpersonationService
{
    /**
     * @return array{impersonate_url: string, expires_in_minutes: int}
     */
    public function issueLink(CustomerUser $user): array
    {
        $hubUrl = LmsHub::configuredUrl();
        $token = (string) config('services.lms.sync_token', '');

        if ($hubUrl === null || $token === '') {
            throw new \RuntimeException('LMS hub URL or sync token is not configured.');
        }

        $payload = [
            'super_admin_user_id' => $user->id,
        ];

        if ($user->lms_user_id !== null) {
            $payload['user_id'] = $user->lms_user_id;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->connectTimeout(10)
            ->post($hubUrl.'/admin-api/impersonate', $payload);

        if (! $response->successful()) {
            Log::warning('LMS impersonation link request failed', [
                'customer_user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException(
                'LMS impersonation link request failed with HTTP '.$response->status()
            );
        }

        $impersonateUrl = $response->json('impersonate_url');

        if (! is_string($impersonateUrl) || $impersonateUrl === '') {
            throw new \RuntimeException('LMS did not return an impersonation link.');
        }

        $lmsUserId = $response->json('user_id');

        if (filled($lmsUserId) && (int) $lmsUserId !== (int) $user->lms_user_id) {
            $user->forceFill(['lms_user_id' => (int) $lmsUserId])->saveQuietly();
        }

        Log::info('LMS impersonation link issued', [
            'customer_user_id' => $user->id,
            'lms_user_id' => $user->fresh()->lms_user_id,
        ]);

        return [
            'impersonate_url' => $impersonateUrl,
            'expires_in_minutes' => (int) ($response->json('expires_in_minutes') ?: 5),
        ];
    }
}
