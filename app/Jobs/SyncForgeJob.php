<?php

namespace App\Jobs;

use App\Helpers\ForgeApi;
use App\Jobs\Concerns\AdvancesDeploymentPipeline;
use App\Jobs\Concerns\LogsSiteDeploymentFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncForgeJob implements ShouldQueue
{
    use AdvancesDeploymentPipeline, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
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

    /**
     * @param  int|null  $customerSubscriptionId  Used for failure logging when this job is part of a deployment pipeline.
     */
    public function __construct(
        public ?int $customerSubscriptionId = null,
        public ?int $deploymentJobId = null
    ) {}

    public function handle(): void
    {
        $forgeApi = new ForgeApi;
        $forgeApi->syncForge();
        Log::info('site_deployment.sync_forge_completed', [
            'customer_subscription_id' => $this->customerSubscriptionId,
        ]);
        $this->advanceDeploymentPipelineAfterSuccess($this->deploymentJobId);
    }
}
