<?php

namespace App\Console\Commands\Generators;

use App\Models\CustomerSubscription;
use App\Services\DeploymentScriptRenderer;
use Illuminate\Console\Command;

class GenerateDeploymentScriptForSites extends Command
{
    protected $signature = 'app:generate-deployment-script-for-sites';

    protected $description = 'Render and push deployment scripts for responder subscriptions (type 3)';

    public function handle(DeploymentScriptRenderer $renderer): int
    {
        $customerSubscriptions = CustomerSubscription::query()
            ->where('subscription_type_id', 3)
            ->get();

        foreach ($customerSubscriptions as $customerSubscription) {
            try {
                $renderer->renderAndPush(
                    $customerSubscription,
                    pushToForge: (bool) ($customerSubscription->server_id && $customerSubscription->forge_site_id)
                );
                $this->info("Rendered script for subscription {$customerSubscription->id}");
            } catch (\Throwable $e) {
                $this->error("Subscription {$customerSubscription->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
