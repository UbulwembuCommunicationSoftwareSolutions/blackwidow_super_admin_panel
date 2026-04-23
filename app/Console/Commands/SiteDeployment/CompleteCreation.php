<?php

namespace App\Console\Commands\SiteDeployment;

use App\Models\CustomerSubscription;
use App\Services\SiteDeploymentScheduler;
use Illuminate\Console\Command;

class CompleteCreation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:complete-creation {customer-subscription-id : Customer subscription to queue the Forge setup pipeline for} {--force : Queue even if a previous deployment was already scheduled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue the same automated Forge site-creation steps used by the admin UI (create site, env, SSL, deploy, etc.).';

    public function handle(SiteDeploymentScheduler $siteDeploymentScheduler): int
    {
        $id = (int) $this->argument('customer-subscription-id');
        $customerSubscription = CustomerSubscription::query()->find($id);
        if (! $customerSubscription) {
            $this->error("Customer subscription {$id} not found.");

            return self::FAILURE;
        }

        try {
            $siteDeploymentScheduler->schedule($customerSubscription, (bool) $this->option('force'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Scheduled site deployment for customer subscription {$id}.");

        return self::SUCCESS;
    }
}
