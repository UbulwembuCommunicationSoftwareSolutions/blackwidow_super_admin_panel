<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DeploymentScript;
use Illuminate\Auth\Access\HandlesAuthorization;

class DeploymentScriptPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DeploymentScript');
    }

    public function view(AuthUser $authUser, DeploymentScript $deploymentScript): bool
    {
        return $authUser->can('View:DeploymentScript');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DeploymentScript');
    }

    public function update(AuthUser $authUser, DeploymentScript $deploymentScript): bool
    {
        return $authUser->can('Update:DeploymentScript');
    }

    public function delete(AuthUser $authUser, DeploymentScript $deploymentScript): bool
    {
        return $authUser->can('Delete:DeploymentScript');
    }

    public function restore(AuthUser $authUser, DeploymentScript $deploymentScript): bool
    {
        return $authUser->can('Restore:DeploymentScript');
    }

    public function forceDelete(AuthUser $authUser, DeploymentScript $deploymentScript): bool
    {
        return $authUser->can('ForceDelete:DeploymentScript');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DeploymentScript');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DeploymentScript');
    }

    public function replicate(AuthUser $authUser, DeploymentScript $deploymentScript): bool
    {
        return $authUser->can('Replicate:DeploymentScript');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DeploymentScript');
    }

}