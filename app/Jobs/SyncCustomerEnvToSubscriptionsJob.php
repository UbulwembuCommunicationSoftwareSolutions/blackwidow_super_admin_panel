<?php

namespace App\Jobs;

use App\Helpers\ForgeApi;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Services\CustomerEnvSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes the customer-level configuration (SECURE_TOKEN + Google Maps key + SMTP credentials)
 * into the env of every subscription the customer owns, then pushes the changed envs to Forge.
 *
 * A failure on one subscription is recorded against that subscription and does not stop the rest.
 */
class SyncCustomerEnvToSubscriptionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $customerId,
        public bool $push = true
    ) {}

    public function handle(CustomerEnvSyncService $envSync): void
    {
        $customer = Customer::query()->find($this->customerId);
        if (! $customer) {
            Log::warning('customer.env_sync.missing_customer', ['customer_id' => $this->customerId]);

            return;
        }

        if ($customer->subscriptionEnvOverrides() === []) {
            Log::info('customer.env_sync.nothing_configured', ['customer_id' => $customer->id]);

            return;
        }

        $forgeApi = null;

        foreach ($customer->customerSubscriptions as $customerSubscription) {
            try {
                $changed = $envSync->syncSubscription($customerSubscription);

                if ($changed === [] || ! $this->push || ! $this->isReadyForForge($customerSubscription)) {
                    continue;
                }

                $forgeApi ??= new ForgeApi;
                $forgeApi->sendEnv($customerSubscription);
            } catch (Throwable $e) {
                $this->recordFailure($customerSubscription, $e);
            }
        }
    }

    private function isReadyForForge(CustomerSubscription $customerSubscription): bool
    {
        if (! $customerSubscription->server_id || ! $customerSubscription->forge_site_id) {
            Log::info('customer.env_sync.skipped_not_on_forge', [
                'customer_subscription_id' => $customerSubscription->id,
            ]);

            return false;
        }

        return true;
    }

    private function recordFailure(CustomerSubscription $customerSubscription, Throwable $e): void
    {
        Log::error('customer.env_sync.failed', [
            'customer_id' => $this->customerId,
            'customer_subscription_id' => $customerSubscription->id,
            'message' => $e->getMessage(),
        ]);

        $customerSubscription->last_deployment_error = $e->getMessage();
        $customerSubscription->last_deployment_error_at = now();
        $customerSubscription->save();
    }
}
