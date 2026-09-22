<?php

namespace App\Services;

use App\Helpers\ForgeApi;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use Illuminate\Support\Facades\Log;

/**
 * Applies the customer-level configuration ({@see Customer::subscriptionEnvOverrides}) — the
 * shared SECURE_TOKEN, Google Maps key, and SMTP credentials — to the {@see EnvVariables}
 * of the customer's subscriptions.
 *
 * Database only: pushing the result to Forge is {@see ForgeApi::sendEnv}.
 *
 * Most override keys are only written when the subscription already has that key (so a static
 * site without mail never gains MAIL_* rows). SECURE_TOKEN is the exception: it is always
 * upserted so every tenant that speaks the sync contract has the customer's shared secret.
 */
class CustomerEnvSyncService
{
    /**
     * @return list<string> the keys whose value changed
     */
    public function syncSubscription(CustomerSubscription $customerSubscription): array
    {
        $customerSubscription->loadMissing('customer');
        $overrides = $customerSubscription->customer?->subscriptionEnvOverrides() ?? [];

        if ($overrides === []) {
            return [];
        }

        $changed = [];

        if (isset($overrides['SECURE_TOKEN'])) {
            $secureTokenChanged = $this->upsertSecureToken(
                $customerSubscription,
                $overrides['SECURE_TOKEN'],
            );
            if ($secureTokenChanged) {
                $changed[] = 'SECURE_TOKEN';
            }
            unset($overrides['SECURE_TOKEN']);
        }

        if ($overrides === []) {
            if ($changed !== []) {
                Log::info('customer.env_sync.applied', [
                    'customer_subscription_id' => $customerSubscription->id,
                    'keys' => $changed,
                ]);
            }

            return $changed;
        }

        $rows = EnvVariables::where('customer_subscription_id', $customerSubscription->id)
            ->whereIn('key', array_keys($overrides))
            ->get();

        foreach ($rows as $row) {
            if ($row->value === $overrides[$row->key]) {
                continue;
            }
            $row->value = $overrides[$row->key];
            $row->save();
            $changed[] = $row->key;
        }

        if ($changed !== []) {
            Log::info('customer.env_sync.applied', [
                'customer_subscription_id' => $customerSubscription->id,
                'keys' => $changed,
            ]);
        }

        return $changed;
    }

    /**
     * @return array<int, list<string>> changed keys per customer subscription id
     */
    public function syncCustomer(Customer $customer): array
    {
        $changed = [];
        foreach ($customer->customerSubscriptions as $customerSubscription) {
            $changed[$customerSubscription->id] = $this->syncSubscription($customerSubscription);
        }

        return $changed;
    }

    private function upsertSecureToken(CustomerSubscription $customerSubscription, string $token): bool
    {
        $row = EnvVariables::query()->firstOrNew([
            'customer_subscription_id' => $customerSubscription->id,
            'key' => 'SECURE_TOKEN',
        ]);

        if (! $row->exists) {
            $row->value = $token;
            $row->save();

            return true;
        }

        if ($row->value === $token) {
            return false;
        }

        $row->value = $token;
        $row->save();

        return true;
    }
}
