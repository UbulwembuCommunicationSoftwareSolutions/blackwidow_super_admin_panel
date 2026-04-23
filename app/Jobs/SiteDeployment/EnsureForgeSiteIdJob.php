<?php

namespace App\Jobs\SiteDeployment;

use App\Helpers\ForgeApi;
use App\Jobs\Concerns\LogsSiteDeploymentFailure;
use App\Models\CustomerSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnsureForgeSiteIdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LogsSiteDeploymentFailure;

    public int $tries = 5;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 40, 60, 90];
    }

    public int $timeout = 300;

    public function __construct(
        public int $customerSubscriptionId
    ) {}

    public function handle(): void
    {
        $customerSubscription = CustomerSubscription::query()->find($this->customerSubscriptionId);
        if (! $customerSubscription) {
            Log::warning('site_deployment.ensure_forge_site.missing_subscription', [
                'customer_subscription_id' => $this->customerSubscriptionId,
            ]);

            return;
        }

        if ($customerSubscription->forge_site_id) {
            Log::info('site_deployment.ensure_forge_site.already_set', [
                'customer_subscription_id' => $customerSubscription->id,
                'forge_site_id' => $customerSubscription->forge_site_id,
            ]);

            return;
        }

        $forgeApi = new ForgeApi;
        if ($forgeApi->tryLinkForgeSiteId($customerSubscription)) {
            Log::info('site_deployment.ensure_forge_site.linked', [
                'customer_subscription_id' => $customerSubscription->id,
            ]);

            return;
        }

        throw new \RuntimeException(
            'Could not link forge_site_id for customer subscription '.$customerSubscription->id.' (attempt '.$this->attempts().'/'.$this->tries.').'
        );
    }
}
