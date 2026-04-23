<?php

namespace App\Jobs;

use App\Helpers\ForgeApi;
use App\Jobs\Concerns\LogsSiteDeploymentFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCommandToForgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LogsSiteDeploymentFailure;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 40, 60];
    }

    public int $timeout = 300;

    public function __construct(
        public int $customerSubscriptionId,
        public string $command
    ) {}

    public function handle(): void
    {
        Log::info('site_deployment.forge_command', [
            'customer_subscription_id' => $this->customerSubscriptionId,
        ]);
        $forgeApi = new ForgeApi;
        $forgeApi->sendCommand($this->customerSubscriptionId, $this->command);
    }
}
