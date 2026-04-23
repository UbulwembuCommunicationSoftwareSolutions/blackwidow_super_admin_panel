<?php

namespace App\Services;

use App\Jobs\SendCommandToForgeJob;
use App\Jobs\SiteDeployment\AddDeploymentScriptOnForgeJob;
use App\Jobs\SiteDeployment\AddEnvVariablesOnForgeJob;
use App\Jobs\SiteDeployment\AddGitRepoOnForgeJob;
use App\Jobs\SiteDeployment\AddSSLOnSiteJob;
use App\Jobs\SiteDeployment\CreateForgeServerDatabaseUserJob;
use App\Jobs\SiteDeployment\CreateSiteOnForgeJob;
use App\Jobs\SiteDeployment\DeploySite;
use App\Jobs\SiteDeployment\EnsureForgeSiteIdJob;
use App\Jobs\SiteDeployment\ProvisionForgeServerDatabaseJob;
use App\Jobs\SiteDeployment\SendSystemConfigJob;
use App\Jobs\SyncForgeJob;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DeploymentStepDispatcher
{
    /**
     * After a pipeline step finishes successfully, mark it completed and start the next step (if any).
     */
    public function completeAndDispatchNext(int $finishedDeploymentJobId): void
    {
        $row = CustomerSubscriptionDeploymentJob::query()->find($finishedDeploymentJobId);
        if (! $row) {
            Log::warning('deployment_step.missing_row', ['deployment_job_id' => $finishedDeploymentJobId]);

            return;
        }

        $row->update([
            'status' => CustomerSubscriptionDeploymentJob::STATUS_COMPLETED,
            'finished_at' => now(),
        ]);

        $next = CustomerSubscriptionDeploymentJob::query()
            ->where('batch_id', $row->batch_id)
            ->where('position', $row->position + 1)
            ->first();

        if (! $next) {
            Log::info('deployment_step.run_finished', [
                'batch_id' => $row->batch_id,
                'customer_subscription_id' => $row->customer_subscription_id,
            ]);

            return;
        }

        $this->dispatchForRow($next);
    }

    public function dispatchForRow(CustomerSubscriptionDeploymentJob $row): void
    {
        $row->update([
            'status' => CustomerSubscriptionDeploymentJob::STATUS_RUNNING,
            'started_at' => $row->started_at ?? now(),
        ]);

        $cid = (int) $row->customer_subscription_id;
        $id = (int) $row->id;
        $params = $row->parameters ?? [];

        match ($row->job_name) {
            SiteDeploymentJobName::PROVISION_FORGE_SERVER_DATABASE => ProvisionForgeServerDatabaseJob::dispatch($cid, $id),
            SiteDeploymentJobName::CREATE_FORGE_SERVER_DATABASE_USER => CreateForgeServerDatabaseUserJob::dispatch($cid, $id),
            SiteDeploymentJobName::CREATE_SITE => CreateSiteOnForgeJob::dispatch($cid, $id),
            SiteDeploymentJobName::ENSURE_FORGE_SITE => EnsureForgeSiteIdJob::dispatch($cid, $id),
            SiteDeploymentJobName::SYNC_FORGE => SyncForgeJob::dispatch($cid, $id),
            SiteDeploymentJobName::ADD_GIT_REPO => AddGitRepoOnForgeJob::dispatch($cid, $id),
            SiteDeploymentJobName::ADD_ENV => AddEnvVariablesOnForgeJob::dispatch($cid, $id),
            SiteDeploymentJobName::ADD_DEPLOYMENT_SCRIPT => AddDeploymentScriptOnForgeJob::dispatch($cid, $id),
            SiteDeploymentJobName::ADD_SSL => AddSSLOnSiteJob::dispatch($cid, $id),
            SiteDeploymentJobName::DEPLOY_SITE => DeploySite::dispatch($cid, $id),
            SiteDeploymentJobName::SEND_FORGE_COMMAND => SendCommandToForgeJob::dispatch(
                $cid,
                (string) ($params['command'] ?? ''),
                $id
            ),
            SiteDeploymentJobName::SEND_SYSTEM_CONFIG => SendSystemConfigJob::dispatch(
                (int) ($params['customer_id'] ?? CustomerSubscription::query()->whereKey($cid)->value('customer_id')),
                $cid,
                $id
            ),
            default => throw new InvalidArgumentException('Unknown deployment job name: '.$row->job_name),
        };

        Log::info('deployment_step.dispatched', [
            'deployment_job_id' => $id,
            'job_name' => $row->job_name,
            'customer_subscription_id' => $cid,
        ]);
    }

    public function markStepFailed(int $deploymentJobId, string $message): void
    {
        $row = CustomerSubscriptionDeploymentJob::query()->find($deploymentJobId);
        if (! $row) {
            return;
        }
        $row->update([
            'status' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
