<?php

namespace App\Policies;

use App\Models\DeploymentTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DeploymentTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {

    }

    public function view(User $user, DeploymentTemplate $deploymentTemplate): bool
    {
    }

    public function create(User $user): bool
    {
    }

    public function update(User $user, DeploymentTemplate $deploymentTemplate): bool
    {
    }

    public function delete(User $user, DeploymentTemplate $deploymentTemplate): bool
    {
    }

    public function restore(User $user, DeploymentTemplate $deploymentTemplate): bool
    {
    }

    public function forceDelete(User $user, DeploymentTemplate $deploymentTemplate): bool
    {
    }
}
