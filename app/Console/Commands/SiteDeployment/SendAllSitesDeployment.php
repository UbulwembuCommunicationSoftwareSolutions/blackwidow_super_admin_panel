<?php

namespace App\Console\Commands\SiteDeployment;

use App\Jobs\PropagateReleaseToDeploymentScriptsJob;
use App\Jobs\SendDeploymentScriptJob;
use App\Models\CustomerSubscription;
use Illuminate\Console\Command;

class SendAllSitesDeployment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-all-sites-deployment {type-id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-render every site\'s deployment script for a subscription type (pinned sites keep their pinned tag) and push it to Forge';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $customerSubscriptions = CustomerSubscription::query()
            ->where('subscription_type_id', $this->argument('type-id'))
            ->orderBy('server_id')
            ->orderBy('id')
            ->get();

        $startAt = now();

        foreach ($customerSubscriptions->values() as $index => $customerSubscription) {
            SendDeploymentScriptJob::dispatch($customerSubscription)
                ->delay($startAt->copy()->addSeconds($index * PropagateReleaseToDeploymentScriptsJob::STAGGER_SECONDS));
        }

        $this->info("Queued deployment script pushes for {$customerSubscriptions->count()} site(s).");

        return self::SUCCESS;
    }
}
