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

class CreateSiteOnForgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LogsSiteDeploymentFailure;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public int $timeout = 300;

    public function __construct(
        public int $customerSubscriptionId
    ) {}

    public function handle(): void
    {
        Log::info('site_deployment.create_site_job', [
            'customer_subscription_id' => $this->customerSubscriptionId,
        ]);
        $customerSubscription = CustomerSubscription::query()->find($this->customerSubscriptionId);
        if (! $customerSubscription) {
            Log::warning('site_deployment.create_site.missing_subscription', [
                'customer_subscription_id' => $this->customerSubscriptionId,
            ]);

            return;
        }
        $forgeApi = new ForgeApi;
        $forgeApi->createSite($customerSubscription->server_id, $customerSubscription);
    }
}
