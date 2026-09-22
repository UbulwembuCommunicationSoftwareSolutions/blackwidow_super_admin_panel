<?php

namespace App\Jobs;

use App\Models\CustomerSubscription;
use App\Services\DeploymentScriptRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDeploymentScriptJob implements ShouldQueue
{
    use Queueable;

    public CustomerSubscription $customerSubscription;

    public function __construct(CustomerSubscription $customerSubscription)
    {
        $this->customerSubscription = $customerSubscription;
    }

    public function handle(DeploymentScriptRenderer $renderer): void
    {
        $renderer->renderAndPush($this->customerSubscription, pushToForge: true);
    }
}
