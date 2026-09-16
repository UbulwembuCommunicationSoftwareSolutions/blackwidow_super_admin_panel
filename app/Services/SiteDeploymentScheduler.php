<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SiteDeploymentScheduler
{
    public const QUEUE_STEP_DELAY_SECONDS = 30;

    /**
     * Schedules only Forge MySQL provision (if needed) and create site, so the run appears in
     * `customer_subscription_deployment_jobs` without the rest of the full pipeline.
     *
     * @throws \RuntimeException When a deployment is already in progress and force is false.
     */
    public function scheduleSiteCreationOnly(CustomerSubscription $customerSubscription, bool $force = true): string
    {
        $this->beginDeploymentRun($customerSubscription, $force);

        $batchId = (string) Str::uuid();
        $build = $this->buildForgeSiteCreationSteps($customerSubscription);
        $this->persistBatchAndDispatchFirst($customerSubscription, $build, $batchId);

        Log::info('site_deployment.scheduled_site_creation_only', [
            'customer_subscription_id' => $customerSubscription->id,
            'batch_id' => $batchId,
            'step_count' => count($build),
        ]);

        return $batchId;
    }

    /**
     * @throws \RuntimeException When a deployment is already in progress and force is false.
     */
    public function schedule(CustomerSubscription $customerSubscription, bool $force = false): string
    {
        $this->beginDeploymentRun($customerSubscription, $force);

        $batchId = (string) Str::uuid();
        $build = array_merge(
            $this->buildForgeSiteCreationSteps($customerSubscription),
            $this->buildPostCreateSiteSteps($customerSubscription)
        );
        $this->persistBatchAndDispatchFirst($customerSubscription, $build, $batchId);

        Log::info('site_deployment.scheduled', [
            'customer_subscription_id' => $customerSubscription->id,
            'batch_id' => $batchId,
            'step_count' => count($build),
        ]);

        return $batchId;
    }

    /**
     * Full `app:complete-creation` step list for this subscription (same order as {@see schedule}).
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public function getCompleteCreationPipelineTemplate(CustomerSubscription $customerSubscription): array
    {
        $customerSubscription->loadMissing('subscriptionType');

        return array_merge(
            $this->buildForgeSiteCreationSteps($customerSubscription),
            $this->buildPostCreateSiteSteps($customerSubscription)
        );
    }

    /**
     * Queue exactly one pipeline step in a new batch (for admin re-runs). Does not call {@see beginDeploymentRun}.
     *
     * @throws InvalidArgumentException If the step index is out of range for this subscription.
     */
    public function queueSingleTemplateStep(CustomerSubscription $customerSubscription, int $index): string
    {
        $template = $this->getCompleteCreationPipelineTemplate($customerSubscription);
        if (! isset($template[$index])) {
            throw new InvalidArgumentException("No pipeline step at index {$index} for customer subscription {$customerSubscription->id}.");
        }

        [$jobName, $params] = $template[$index];
        $batchId = (string) Str::uuid();
        $row = CustomerSubscriptionDeploymentJob::query()->create([
            'customer_subscription_id' => $customerSubscription->id,
            'batch_id' => $batchId,
            'position' => 0,
            'job_name' => $jobName,
            'parameters' => $params === [] ? null : $params,
            'status' => CustomerSubscriptionDeploymentJob::STATUS_PENDING,
        ]);

        app(DeploymentStepDispatcher::class)->dispatchForRow($row);

        Log::info('site_deployment.single_template_step_queued', [
            'customer_subscription_id' => $customerSubscription->id,
            'batch_id' => $batchId,
            'step_index' => $index,
            'job_name' => $jobName,
        ]);

        return $batchId;
    }

    /**
     * Re-dispatch a deployment job row in place so later steps in the same batch resume on success.
     *
     * @throws \RuntimeException If any row in the same batch is currently running.
     */
    public function retryDeploymentJob(CustomerSubscriptionDeploymentJob $row): string
    {
        $batchRunning = CustomerSubscriptionDeploymentJob::query()
            ->where('batch_id', $row->batch_id)
            ->where('status', CustomerSubscriptionDeploymentJob::STATUS_RUNNING)
            ->exists();

        if ($batchRunning) {
            throw new \RuntimeException(
                'Cannot retry deployment job '.$row->id.' while another step in batch '.$row->batch_id.' is running.'
            );
        }

        $row->update([
            'status' => CustomerSubscriptionDeploymentJob::STATUS_PENDING,
            'error_message' => null,
            'forge_status' => null,
            'forge_log' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);
        $row->refresh();

        $subscription = $row->customerSubscription;
        if ($subscription) {
            $subscription->last_deployment_error = null;
            $subscription->last_deployment_error_at = null;
            $subscription->save();
        }

        app(DeploymentStepDispatcher::class)->dispatchForRow($row);

        Log::info('site_deployment.job_retried', [
            'customer_subscription_id' => $row->customer_subscription_id,
            'batch_id' => $row->batch_id,
            'deployment_job_id' => $row->id,
            'job_name' => $row->job_name,
            'position' => $row->position,
        ]);

        return (string) $row->batch_id;
    }

    /**
     * Copy a deployment job into a new single-step batch (does not resume the original batch).
     */
    public function requeueDeploymentJobAlone(CustomerSubscriptionDeploymentJob $row): string
    {
        $batchId = (string) Str::uuid();
        $copy = CustomerSubscriptionDeploymentJob::query()->create([
            'customer_subscription_id' => $row->customer_subscription_id,
            'batch_id' => $batchId,
            'position' => 0,
            'job_name' => $row->job_name,
            'parameters' => $row->parameters,
            'status' => CustomerSubscriptionDeploymentJob::STATUS_PENDING,
        ]);

        app(DeploymentStepDispatcher::class)->dispatchForRow($copy);

        Log::info('site_deployment.job_requeued_alone', [
            'customer_subscription_id' => $row->customer_subscription_id,
            'batch_id' => $batchId,
            'source_deployment_job_id' => $row->id,
            'deployment_job_id' => $copy->id,
            'job_name' => $row->job_name,
        ]);

        return $batchId;
    }

    /**
     * @throws \RuntimeException
     */
    private function beginDeploymentRun(CustomerSubscription $customerSubscription, bool $force): void
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
    }

    /**
     * Shared prefix for the full pipeline and for `scheduleSiteCreationOnly()`.
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function buildForgeSiteCreationSteps(CustomerSubscription $customerSubscription): array
    {
        $customerSubscription->loadMissing('subscriptionType');
        $needsServerDatabase = $customerSubscription->isPhpSubscriptionWithDatabase();
        $build = [];
        if ($needsServerDatabase) {
            $build[] = [SiteDeploymentJobName::PROVISION_FORGE_SERVER_DATABASE, []];
            $build[] = [SiteDeploymentJobName::CREATE_FORGE_SERVER_DATABASE_USER, []];
        }
        $build[] = [SiteDeploymentJobName::CREATE_SITE, $needsServerDatabase ? ['skip_database_provision' => true] : []];

        return $build;
    }

    /**
     * Steps that follow create site in the full complete-creation pipeline.
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function buildPostCreateSiteSteps(CustomerSubscription $customerSubscription): array
    {
        $customerId = (int) $customerSubscription->customer_id;
        $build = [
            [SiteDeploymentJobName::ENSURE_FORGE_SITE, []],
            [SiteDeploymentJobName::SYNC_FORGE, []],
            [SiteDeploymentJobName::ADD_GIT_REPO, []],
            [SiteDeploymentJobName::ADD_ENV, []],
            [SiteDeploymentJobName::ADD_DEPLOYMENT_SCRIPT, []],
            [SiteDeploymentJobName::ADD_SSL, []],
            [SiteDeploymentJobName::DEPLOY_SITE, []],
        ];

        if (in_array($customerSubscription->subscription_type_id, [1, 2, 9, 10, 11], true)) {
            $build[] = [SiteDeploymentJobName::SEND_FORGE_COMMAND, ['command' => 'php artisan key:generate --force']];
            $build[] = [SiteDeploymentJobName::SEND_FORGE_COMMAND, ['command' => 'php artisan migrate --force']];
            $build[] = [SiteDeploymentJobName::SEND_FORGE_COMMAND, ['command' => 'php artisan db:seed BaseLineSeeder --force']];
            $build[] = [SiteDeploymentJobName::DEPLOY_SITE, []];
            $build[] = [SiteDeploymentJobName::SEND_SYSTEM_CONFIG, ['customer_id' => $customerId]];
            $build[] = [SiteDeploymentJobName::SEND_FORGE_COMMAND, ['command' => 'php artisan storage:link']];
        }

        return $build;
    }

    /**
     * @param  list<array{0: string, 1: array<string, mixed>}>  $build
     */
    private function persistBatchAndDispatchFirst(CustomerSubscription $customerSubscription, array $build, string $batchId): void
    {
        $cid = $customerSubscription->id;

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
    }
}
