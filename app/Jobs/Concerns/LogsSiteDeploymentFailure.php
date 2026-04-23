<?php

namespace App\Jobs\Concerns;

use App\Models\CustomerSubscription;
use Illuminate\Support\Facades\Log;
use Throwable;

trait LogsSiteDeploymentFailure
{
    public function failed(?Throwable $exception): void
    {
        if (! property_exists($this, 'customerSubscriptionId') || $this->customerSubscriptionId === null) {
            if ($exception) {
                Log::error('site_deployment.job_failed', [
                    'job' => static::class,
                    'message' => $exception->getMessage(),
                ]);
            }

            return;
        }

        $id = (int) $this->customerSubscriptionId;
        $subscription = CustomerSubscription::query()->find($id);
        if ($subscription) {
            $subscription->last_deployment_error = $exception?->getMessage() ?? 'Unknown job failure';
            $subscription->last_deployment_error_at = now();
            $subscription->save();
        }

        Log::error('site_deployment.job_failed', [
            'customer_subscription_id' => $id,
            'job' => static::class,
            'message' => $exception?->getMessage(),
        ]);
    }
}
