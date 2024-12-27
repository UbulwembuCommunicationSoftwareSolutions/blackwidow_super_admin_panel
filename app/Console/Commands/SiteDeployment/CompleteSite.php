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
use App\Models\CustomerSubscription;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class CompleteSite extends Command
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
        CreateSiteOnForgeJob::dispatch($customerSubscription->id);
        $jobs[] = array(
            'id' => CreateSiteOnForgeJob::dispatch($customerSubscription->id),
            'progress' => 0
        );
        $jobs[] = array(
            'id' => SyncForge::dispatch($customerSubscription->id)->delay(now()->addSeconds(30)),
            'progress' => 0
        );

        $jobs[] = array(
            'id' => AddGitRepoOnForgeJob::dispatch($customerSubscription->id)->delay(now()->addMinutes(2)),
            'progress' => 0
        );


        $jobs[] = array(
            'id' => AddEnvVariablesOnForgeJob::dispatch($customerSubscription->id)->delay(now()->addMinutes(3)),
            'progress' => 0
        );

        $jobs[] = array(
            'id' => AddDeploymentScriptOnForgeJob::dispatch($customerSubscription->id)->delay(now()->addMinutes(4)),
            'progress' => 0
        );

        $jobs[] = array(
            'id' => AddSSLOnSiteJob::dispatch($customerSubscription->id)->delay(now()->addMinutes(5)),
            'progress' => 0
        );

        $jobs[] = array(
            'id' => DeploySite::dispatch($customerSubscription->id)->delay(now()->addMinutes(6)),
            'progress' => 0
        );

        if($this->record->subscription_type_id == 1){
            $jobs[] = array(
                'id' => SendCommandToForgeJob::dispatch($customerSubscription->id,'php artisan key:generate --force')->delay(now()->addMinutes(7)),
                'progress' => 0
            );

            $jobs[] = array(
                'id' => SendCommandToForgeJob::dispatch($customerSubscription->id,'php artisan migrate --force')->delay(now()->addMinutes(8)),
                'progress' => 0
            );

            $jobs[] = array(
                'id' => SendCommandToForgeJob::dispatch($customerSubscription->id,'php artisan db:seed BaseLineSeeder --force')->delay(now()->addMinutes(9)),
                'progress' => 0
            );
            $jobs[] = array(
                'id' => DeploySite::dispatch($customerSubscription->id)->delay(now()->addMinutes(10)),
                'progress' => 0
            );
        }

    }
}
