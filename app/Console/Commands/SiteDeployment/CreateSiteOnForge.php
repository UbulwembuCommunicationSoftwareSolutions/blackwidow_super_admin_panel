<?php

namespace App\Console\Commands\SiteDeployment;

use App\Models\CustomerSubscription;
use App\Services\SiteDeploymentScheduler;
use Illuminate\Console\Command;

class CreateSiteOnForge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-site-on-forge {customer-subscription-id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(SiteDeploymentScheduler $siteDeploymentScheduler): int
    {
        $id = (int) $this->argument('customer-subscription-id');
        $customerSubscription = CustomerSubscription::query()->find($id);
        if (! $customerSubscription) {
            $this->error('Customer subscription not found: '.$id);

            return self::FAILURE;
        }
        $batchId = $siteDeploymentScheduler->scheduleSiteCreationOnly($customerSubscription, true);
        $this->info('Site creation batch scheduled: '.$batchId);

        return self::SUCCESS;
    }
}
