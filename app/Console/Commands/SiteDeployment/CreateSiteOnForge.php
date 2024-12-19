<?php

namespace App\Console\Commands\SiteDeployment;

use App\Jobs\SiteDeployment\CreateSiteOnForgeJob;
use App\Models\CustomerSubscription;
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
    public function handle()
    {
        $customerSubscription = CustomerSubscription::find($this->argument('customer-subscription-id'));
        dd($customerSubscription->server_id);
        CreateSiteOnForgeJob::dispatch($customerSubscription->id);
    }
}
