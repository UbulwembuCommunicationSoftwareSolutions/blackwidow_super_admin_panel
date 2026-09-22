<?php

namespace App\Console\Commands\SiteDeployment;

use App\Models\CustomerSubscription;
use App\Services\DeploymentScriptRenderer;
use Illuminate\Console\Command;

class SendSiteDeploymentScript extends Command
{
    protected $signature = 'app:send-site-deployment-script {customer-subscription-id}';

    protected $description = 'Render and push the deployment script for a single customer subscription';

    public function handle(DeploymentScriptRenderer $renderer): int
    {
        $customerSubscription = CustomerSubscription::query()
            ->findOrFail($this->argument('customer-subscription-id'));

        $renderer->renderAndPush(
            $customerSubscription,
            pushToForge: (bool) ($customerSubscription->server_id && $customerSubscription->forge_site_id)
        );

        $this->info("Deployment script rendered for subscription {$customerSubscription->id}");

        return self::SUCCESS;
    }
}
