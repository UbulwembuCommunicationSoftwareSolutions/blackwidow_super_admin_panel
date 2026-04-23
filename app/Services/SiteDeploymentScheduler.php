<?php

namespace App\Services;

use App\Jobs\SendCommandToForgeJob;
use App\Jobs\SiteDeployment\AddDeploymentScriptOnForgeJob;
use App\Jobs\SiteDeployment\AddEnvVariablesOnForgeJob;
use App\Jobs\SiteDeployment\AddGitRepoOnForgeJob;
use App\Jobs\SiteDeployment\AddSSLOnSiteJob;
use App\Jobs\SiteDeployment\CreateSiteOnForgeJob;
use App\Jobs\SiteDeployment\DeploySite;
use App\Jobs\SiteDeployment\EnsureForgeSiteIdJob;
use App\Jobs\SiteDeployment\SendSystemConfigJob;
use App\Jobs\SyncForgeJob;
use App\Models\CustomerSubscription;
use Illuminate\Support\Facades\Log;

class SiteDeploymentScheduler
{
    public const QUEUE_STEP_DELAY_SECONDS = 30;

    /**
     * Queue the standard Forge site deployment pipeline and persist a trackable manifest on the subscription.
     *
     * @throws \RuntimeException When a deployment is already in progress and force is false.
     */
    public function schedule(CustomerSubscription $customerSubscription, bool $force = false): void
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

        $manifest = [
            'customer_subscription_id' => $customerSubscription->id,
            'started_at' => now()->toIso8601String(),
            'steps' => [],
        ];

        $cid = $customerSubscription->id;
        $delay = 0;

        $this->pushStep($manifest, CreateSiteOnForgeJob::class, $delay);
        CreateSiteOnForgeJob::dispatch($cid)->delay(now()->addSeconds($delay));

        $delay = 20;
        $this->pushStep($manifest, EnsureForgeSiteIdJob::class, $delay);
        EnsureForgeSiteIdJob::dispatch($cid)->delay(now()->addSeconds($delay));

        $delay = self::QUEUE_STEP_DELAY_SECONDS;
        $this->pushStep($manifest, SyncForgeJob::class, $delay);
        SyncForgeJob::dispatch($cid)->delay(now()->addSeconds($delay));

        $delay += self::QUEUE_STEP_DELAY_SECONDS;
        $this->pushStep($manifest, AddGitRepoOnForgeJob::class, $delay);
        AddGitRepoOnForgeJob::dispatch($cid)->delay(now()->addSeconds($delay));

        $delay += self::QUEUE_STEP_DELAY_SECONDS;
        $this->pushStep($manifest, AddEnvVariablesOnForgeJob::class, $delay);
        AddEnvVariablesOnForgeJob::dispatch($cid)->delay(now()->addSeconds($delay));

        $delay += self::QUEUE_STEP_DELAY_SECONDS;
        $this->pushStep($manifest, AddDeploymentScriptOnForgeJob::class, $delay);
        AddDeploymentScriptOnForgeJob::dispatch($cid)->delay(now()->addSeconds($delay));

        $delay += self::QUEUE_STEP_DELAY_SECONDS;
        $this->pushStep($manifest, AddSSLOnSiteJob::class, $delay);
        AddSSLOnSiteJob::dispatch($cid)->delay(now()->addSeconds($delay));

        $delay += self::QUEUE_STEP_DELAY_SECONDS;
        $this->pushStep($manifest, DeploySite::class, $delay);
        DeploySite::dispatch($cid)->delay(now()->addSeconds($delay));

        if (in_array($customerSubscription->subscription_type_id, [1, 2, 9, 10, 11], true)) {
            $delay += self::QUEUE_STEP_DELAY_SECONDS;
            $this->pushStep($manifest, SendCommandToForgeJob::class.'#key:generate', $delay);
            SendCommandToForgeJob::dispatch($cid, 'php artisan key:generate --force')->delay(now()->addSeconds($delay));

            $delay += self::QUEUE_STEP_DELAY_SECONDS;
            $this->pushStep($manifest, SendCommandToForgeJob::class.'#migrate', $delay);
            SendCommandToForgeJob::dispatch($cid, 'php artisan migrate --force')->delay(now()->addSeconds($delay));

            $delay += self::QUEUE_STEP_DELAY_SECONDS;
            $this->pushStep($manifest, SendCommandToForgeJob::class.'#db:seed', $delay);
            SendCommandToForgeJob::dispatch($cid, 'php artisan db:seed BaseLineSeeder --force')->delay(now()->addSeconds($delay));

            $delay += self::QUEUE_STEP_DELAY_SECONDS;
            $this->pushStep($manifest, DeploySite::class.'#post-seed', $delay);
            DeploySite::dispatch($cid)->delay(now()->addSeconds($delay));

            $delay += self::QUEUE_STEP_DELAY_SECONDS;
            $this->pushStep($manifest, SendSystemConfigJob::class, $delay);
            SendSystemConfigJob::dispatch($customerSubscription->customer_id)->delay(now()->addSeconds($delay));

            $delay += self::QUEUE_STEP_DELAY_SECONDS;
            $this->pushStep($manifest, SendCommandToForgeJob::class.'#storage:link', $delay);
            SendCommandToForgeJob::dispatch($cid, 'php artisan storage:link')->delay(now()->addSeconds($delay));
        }

        $customerSubscription->jobs = json_encode($manifest);
        $customerSubscription->save();

        Log::info('site_deployment.scheduled', [
            'customer_subscription_id' => $cid,
            'step_count' => count($manifest['steps']),
        ]);
    }

    /**
     * @param  array{customer_subscription_id: int, started_at: string, steps: list<array{job: string, delay_from_start_seconds: int, status: string}>}  $manifest
     */
    private function pushStep(array &$manifest, string $job, int $delayFromStart): void
    {
        $manifest['steps'][] = [
            'job' => $job,
            'delay_from_start_seconds' => $delayFromStart,
            'status' => 'queued',
        ];
    }
}
