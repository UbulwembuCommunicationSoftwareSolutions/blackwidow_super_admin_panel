<?php

namespace App\Console\Commands\SiteDeployment;

use App\Helpers\ForgeApi;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use Illuminate\Console\Command;

class SendSiteDeploymentScript extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-site-deployment-script {customer-subscription-id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle():void
    {
        $customerSubscription = CustomerSubscription::findOrFail($this->argument('customer-subscription-id'));

    }
}
