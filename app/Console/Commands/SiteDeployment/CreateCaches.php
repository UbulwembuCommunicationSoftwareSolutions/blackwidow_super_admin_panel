<?php

namespace App\Console\Commands\SiteDeployment;

use App\Console\Commands\ForgeGetters\SyncForge;
use App\Jobs\SendCommandToForgeJob;
use App\Jobs\SiteDeployment\AddDeploymentScriptOnForgeJob;
use App\Jobs\SiteDeployment\AddEnvVariablesOnForgeJob;
use App\Jobs\SiteDeployment\AddGitRepoOnForgeJob;
use App\Jobs\SiteDeployment\AddSSLOnSiteJob;
use App\Jobs\SiteDeployment\CreateSiteOnForgeJob;
use App\Jobs\SiteDeployment\DeploySite;
use App\Jobs\SyncForgeJob;
use App\Models\CustomerSubscription;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class CreateCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-cache {customer-subscription-id}';

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
        $jobs[] = array(
            'id' => SendCommandToForgeJob::dispatch($customerSubscription->id,'mkdir storage/framework')->delay(now()->addMinutes(7)),
            'progress' => 0
        );
        $jobs[] = array(
            'id' => SendCommandToForgeJob::dispatch($customerSubscription->id,'mkdir storage/framework/cache')->delay(now()->addMinutes(7)),
            'progress' => 0
        );
        $jobs[] = array(
            'id' => SendCommandToForgeJob::dispatch($customerSubscription->id,'mkdir storage/framework/views')->delay(now()->addMinutes(7)),
            'progress' => 0
        );
        $jobs[] = array(
            'id' => SendCommandToForgeJob::dispatch($customerSubscription->id,'mkdir storage/framework/sessions')->delay(now()->addMinutes(7)),
            'progress' => 0
        );

    }
}
