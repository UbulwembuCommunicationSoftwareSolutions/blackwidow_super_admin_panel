<?php

namespace App\Jobs\Concerns;

use App\Services\DeploymentStepDispatcher;

trait AdvancesDeploymentPipeline
{
    protected function advanceDeploymentPipelineAfterSuccess(?int $deploymentJobId): void
    {
        if ($deploymentJobId === null) {
            return;
        }
        app(DeploymentStepDispatcher::class)->completeAndDispatchNext($deploymentJobId);
    }
}
