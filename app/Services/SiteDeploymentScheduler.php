<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SiteDeploymentScheduler
{
    public const QUEUE_STEP_DELAY_SECONDS = 30;

    /**
     * @throws \RuntimeException When a deployment is already in progress and force is false.
     */
    public function schedule(CustomerSubscription $customerSubscription, bool $force = false): string
    {
        if (! $force && $customerSubscription->site_deployment_queue_started_at !== null) {
            throw new \RuntimeException(
                'Site deployment was already scheduled for customer subscription '.$customerSubscription->id.
                ' Pass force to queue again, or clear site_deployment_queue_started_at if the previous run failed.'
            );
        }

        $customerSubscription->site_deployment_queue_started_at = now();
        $customerSubscription->last_deployment_error = null;
        $customerSubscription->last_deployment_error_at = null;
        $customerSubscription->save();

        $batchId = (string) Str::uuid();
        $cid = $customerSubscription->id;
        $customerId = (int) $customerSubscription->customer_id;

        $customerSubscription->loadMissing('subscriptionType');
        $needsServerDatabase = $customerSubscription->isPhpSubscriptionWithDatabase();
        $build = [];
        if ($needsServerDatabase) {
            $build[] = [SiteDeploymentJobName::PROVISION_FORGE_SERVER_DATABASE, []];
        }
        $build[] = [SiteDeploymentJobName::CREATE_SITE, $needsServerDatabase ? ['skip_database_provision' => true] : []];
        $build[] = [SiteDeploymentJobName::ENSURE_FORGE_SITE, []];
        $build[] = [SiteDeploymentJobName::SYNC_FORGE, []];
        $build[] = [SiteDeploymentJobName::ADD_GIT_REPO, []];
        $build[] = [SiteDeploymentJobName::ADD_ENV, []];
        $build[] = [SiteDeploymentJobName::ADD_DEPLOYMENT_SCRIPT, []];
        $build[] = [SiteDeploymentJobName::ADD_SSL, []];
        $build[] = [SiteDeploymentJobName::DEPLOY_SITE, []];

        if (in_array($customerSubscription->subscription_type_id, [1, 2, 9, 10, 11], true)) {
            $build[] = [SiteDeploymentJobName::SEND_FORGE_COMMAND, ['command' => 'php artisan key:generate --force']];
            $build[] = [SiteDeploymentJobName::SEND_FORGE_COMMAND, ['command' => 'php artisan migrate --force']];
            $build[] = [SiteDeploymentJobName::SEND_FORGE_COMMAND, ['command' => 'php artisan db:seed BaseLineSeeder --force']];
            $build[] = [SiteDeploymentJobName::DEPLOY_SITE, []];
            $build[] = [SiteDeploymentJobName::SEND_SYSTEM_CONFIG, ['customer_id' => $customerId]];
            $build[] = [SiteDeploymentJobName::SEND_FORGE_COMMAND, ['command' => 'php artisan storage:link']];
        }

        foreach ($build as $position => $item) {
            [$jobName, $params] = $item;
            CustomerSubscriptionDeploymentJob::query()->create([
                'customer_subscription_id' => $cid,
                'batch_id' => $batchId,
                'position' => $position,
                'job_name' => $jobName,
                'parameters' => $params === [] ? null : $params,
                'status' => CustomerSubscriptionDeploymentJob::STATUS_PENDING,
            ]);
        }

        $first = CustomerSubscriptionDeploymentJob::query()
            ->where('batch_id', $batchId)
            ->orderBy('position')
            ->first();

        if (! $first) {
            throw new \RuntimeException('No deployment steps were created for customer subscription '.$cid.'.');
        }

        app(DeploymentStepDispatcher::class)->dispatchForRow($first);

        Log::info('site_deployment.scheduled', [
            'customer_subscription_id' => $cid,
            'batch_id' => $batchId,
            'step_count' => count($build),
        ]);

        return $batchId;
    }
}
