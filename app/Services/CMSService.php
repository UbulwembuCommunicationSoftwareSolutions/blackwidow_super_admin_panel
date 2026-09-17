<?php

namespace App\Services;

use App\Mail\CustomerPasswordResetMail;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CMSService
{
    public function setConsoleSystemConfigs($subscription)
    {
        $subscription->loadMissing('customer');

        if (blank($subscription->url)) {
            Log::warning('CMS system config sync skipped: empty subscription URL', [
                'subscription_id' => $subscription->id,
            ]);

            throw new \RuntimeException('CMS system config sync skipped: empty subscription URL');
        }

        if (! $subscription->customer || blank($subscription->customer->token)) {
            Log::warning('CMS system config sync skipped: missing customer or API token', [
                'subscription_id' => $subscription->id,
            ]);

            throw new \RuntimeException('CMS system config sync skipped: missing customer or API token');
        }

        $url = rtrim((string) $subscription->url, '/').'/admin-api/set-levels';
        $data = [
            'level_one_in_use' => $subscription->customer->level_one_in_use,
            'level_one_description' => $subscription->customer->level_one_description,
            'level_two_in_use' => $subscription->customer->level_two_in_use,
            'level_two_description' => $subscription->customer->level_two_description,
            'level_three_in_use' => $subscription->customer->level_three_in_use,
            'level_three_description' => $subscription->customer->level_three_description,
            'level_four_description' => $subscription->customer->level_four_description,
            'level_five_description' => $subscription->customer->level_five_description,
            'task_description' => $subscription->customer->task_description,
            'docket_description' => $subscription->customer->docket_description,
        ];

        $response = Http::withToken((string) $subscription->customer->token)
            ->acceptJson()
            ->asJson()
            ->post($url, $data);

        if (! $response->successful()) {
            Log::warning('CMS system config sync failed', [
                'subscription_id' => $subscription->id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException(
                'CMS system config sync failed with HTTP '.$response->status()
            );
        }

        Log::info('CMS system config sync succeeded', [
            'subscription_id' => $subscription->id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    public static function suspendService($customerUser)
    {
        $subscription = CustomerSubscription::where('customer_id', $customerUser->customer_id)
            ->where('subscription_type_id', 1)
            ->first();
        $url = $subscription->url.'/admin-api/suspend-service';
        echo 'Doing request to '.$url.' with token '.$subscription->customer->token.PHP_EOL;
        $data = [
            'email' => $customerUser->email_address,
        ];
        $response = Http::withToken($subscription->customer->token)->post($url, $data);
        Log::info($response->body());
    }

    /**
     * Email a console user the link that lets them set their password.
     *
     * Only the console owns the password_reset_tokens table, so it mints the
     * token and hands the link back; we do the sending. A console that is not
     * linked to us still sends its own email and returns no link, in which case
     * there is nothing left for us to do.
     */
    public function sendWelcomeEmail(CustomerUser $customerUser): void
    {
        $subscription = CustomerSubscription::where('subscription_type_id', 1)
            ->where('customer_id', $customerUser->customer_id)
            ->first();

        if (! $subscription || blank($subscription->url)) {
            Log::warning('Console password reset email skipped: no console subscription URL', [
                'customer_user_id' => $customerUser->id,
                'customer_id' => $customerUser->customer_id,
            ]);

            return;
        }

        $subscription->loadMissing('customer');

        if (! $subscription->customer || blank($subscription->customer->token)) {
            Log::warning('Console password reset email skipped: missing customer or API token', [
                'customer_user_id' => $customerUser->id,
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        $url = rtrim((string) $subscription->url, '/').'/admin-api/send-welcome-email';

        $response = Http::withToken((string) $subscription->customer->token)
            ->acceptJson()
            ->asJson()
            ->post($url, ['email' => $customerUser->email_address]);

        if (! $response->successful()) {
            Log::warning('Console password reset link request failed', [
                'customer_user_id' => $customerUser->id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException(
                'Console password reset link request failed with HTTP '.$response->status()
            );
        }

        $resetUrl = $response->json('reset_url');

        if (! is_string($resetUrl) || $resetUrl === '') {
            Log::info('Console sent its own password reset email; nothing to send here', [
                'customer_user_id' => $customerUser->id,
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        $customerUser->loadMissing('customer');

        Mail::to($customerUser->email_address)->send(new CustomerPasswordResetMail(
            $customerUser,
            $resetUrl,
            (string) ($subscription->app_name ?: 'Console'),
            (int) ($response->json('expires_in_minutes') ?: config('auth.passwords.users.expire')),
        ));

        Log::info('Console password reset email sent', [
            'customer_user_id' => $customerUser->id,
            'subscription_id' => $subscription->id,
        ]);
    }

    public static function syncUsers($id)
    {
        $customer = Customer::find($id);
        if ($customer) {
            $subscription = CustomerSubscription::where('subscription_type_id', 1)
                ->where('customer_id', $customer->id)
                ->first();
            if ($subscription) {
                $url = $subscription->url.'/admin-api/sync-users';
                Log::info('Doing request to '.$url.' with token '.$subscription->customer->token);
                $response = Http::withToken($subscription->customer->token)->post($url);
                Log::info($response->body());
            }
        }
    }

    public static function syncPanicButtonEnabled(CustomerSubscription $subscription): void
    {
        if ((int) $subscription->subscription_type_id !== 1) {
            return;
        }

        if (blank($subscription->url)) {
            Log::warning('CMS panic sync skipped: empty subscription URL', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        $subscription->loadMissing('customer');

        if (! $subscription->customer || blank($subscription->customer->token)) {
            Log::warning('CMS panic sync skipped: missing customer or API token', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        $url = rtrim((string) $subscription->url, '/').'/admin-api/set-panic-button-enabled';

        try {
            $response = Http::withToken((string) $subscription->customer->token)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->post($url, [
                    'panic_button_enabled' => (bool) $subscription->panic_button_enabled,
                ]);
        } catch (\Throwable $e) {
            Log::warning('CMS panic sync unreachable', [
                'subscription_id' => $subscription->id,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            Log::warning('CMS panic sync failed', [
                'subscription_id' => $subscription->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    /**
     * Ask a customer's console install to mint a one-time login link for one
     * of its users, so an operator here can open a session as them without
     * ever holding console credentials themselves.
     *
     * @return array{impersonate_url: string, expires_in_minutes: int}
     */
    public function impersonate(CustomerUser $customerUser, CustomerSubscription $subscription): array
    {
        $subscription->loadMissing('customer');

        if (blank($subscription->url) || ! $subscription->customer || blank($subscription->customer->token)) {
            throw new \RuntimeException('This subscription is missing a URL or API token.');
        }

        if (blank($customerUser->cms_user_id)) {
            throw new \RuntimeException('This user has not been synced to the console yet.');
        }

        $url = rtrim((string) $subscription->url, '/').'/admin-api/impersonate';

        $response = Http::withToken((string) $subscription->customer->token)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->connectTimeout(10)
            ->post($url, [
                'user_id' => $customerUser->cms_user_id,
                'super_admin_user_id' => $customerUser->id,
            ]);

        if (! $response->successful()) {
            Log::warning('Console impersonation link request failed', [
                'customer_user_id' => $customerUser->id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException(
                'Console impersonation link request failed with HTTP '.$response->status()
            );
        }

        $impersonateUrl = $response->json('impersonate_url');

        if (! is_string($impersonateUrl) || $impersonateUrl === '') {
            throw new \RuntimeException('Console did not return an impersonation link.');
        }

        Log::info('Console impersonation link issued', [
            'customer_user_id' => $customerUser->id,
            'subscription_id' => $subscription->id,
        ]);

        return [
            'impersonate_url' => $impersonateUrl,
            'expires_in_minutes' => (int) ($response->json('expires_in_minutes') ?: 5),
        ];
    }

    public function sendAppLink(CustomerUser $customerUser, CustomerSubscription $customerSubscription)
    {

        $url = $customerSubscription->url;
        $customerSubscription->loadMissing('customer');
        Log::info('Doing request to '.$url.' with token '.$customerSubscription->customer->token);
        $data = [
            'email' => $customerUser->email_address,
        ];
        $response = Http::withToken($customerSubscription->customer->token)->post($url, $data);
        Log::info($response->body());
    }
}
