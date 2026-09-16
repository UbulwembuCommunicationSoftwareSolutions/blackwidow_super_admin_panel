<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function sendWelcomeEmail(CustomerUser $customerUser)
    {
        $subscription = CustomerSubscription::where('subscription_type_id', 1)
            ->where('customer_id', $customerUser->customer_id)
            ->first();
        $url = $subscription->url.'/admin-api/send-welcome-email';
        Log::info('Doing request to '.$url.' with token '.$subscription->customer->token);
        $data = [
            'email' => $customerUser->email_address,
        ];
        $response = Http::withToken($subscription->customer->token)->post($url, $data);
        Log::info($response->body());
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
