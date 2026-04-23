<?php

namespace App\Jobs\SiteDeployment;

use App\Helpers\ForgeApi;
use App\Jobs\Concerns\LogsSiteDeploymentFailure;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AddDeploymentScriptOnForgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LogsSiteDeploymentFailure;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 40, 60];
    }

    public int $timeout = 300;

    public function __construct(
        public int $customerSubscriptionId
    ) {}

    public function handle(): void
    {
        $customerSubscription = CustomerSubscription::query()->find($this->customerSubscriptionId);
        if (! $customerSubscription) {
            Log::warning('site_deployment.add_deploy_script.missing_subscription', [
                'customer_subscription_id' => $this->customerSubscriptionId,
            ]);

            return;
        }

        Log::info('site_deployment.add_deployment_script', [
            'customer_subscription_id' => $this->customerSubscriptionId,
        ]);
        $forgeApi = new ForgeApi;
        $script = DeploymentScript::where('customer_subscription_id', $customerSubscription->id)->first();
        if (! $script) {
            $deploymentTemplate = DeploymentTemplate::where('subscription_type_id', $customerSubscription->subscription_type_id)->first();
            if (! $deploymentTemplate) {
                throw new \RuntimeException(
                    'No deployment template for subscription type '.$customerSubscription->subscription_type_id.' (customer subscription '.$customerSubscription->id.').'
                );
            }
            $siteDeployment = str_replace('#WEBSITE_URL#', $customerSubscription->domain, $deploymentTemplate->script);
            $script = DeploymentScript::updateOrCreate(
                [
                    'customer_subscription_id' => $customerSubscription->id,
                ],
                [
                    'script' => $siteDeployment,
                ]
            );
            $script->save();
        }
        if ($script) {
            $forgeApi->sendDeploymentScript($customerSubscription);
        }
    }
}
