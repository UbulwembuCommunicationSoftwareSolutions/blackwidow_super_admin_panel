<?php

namespace App\Services;

use App\Helpers\ForgeApi;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use Illuminate\Support\Facades\Log;

/**
 * Applies the customer-level configuration ({@see Customer::subscriptionEnvOverrides}) — the Google
 * Maps key and the SMTP credentials — to the {@see EnvVariables} of the customer's subscriptions.
 *
 * Database only: pushing the result to Forge is {@see ForgeApi::sendEnv}.
 */
class CustomerEnvSyncService
{
    /**
     * Only keys the subscription already has are written, so a subscription type whose template
     * omits a key (e.g. a static site with no mail) never gains it.
     *
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
}
