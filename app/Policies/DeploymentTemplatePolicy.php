<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DeploymentTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class DeploymentTemplatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DeploymentTemplate');
    }

    public function view(AuthUser $authUser, DeploymentTemplate $deploymentTemplate): bool
    {
        return $authUser->can('View:DeploymentTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DeploymentTemplate');
    }

    public function update(AuthUser $authUser, DeploymentTemplate $deploymentTemplate): bool
    {
        return $authUser->can('Update:DeploymentTemplate');
    }

    public function delete(AuthUser $authUser, DeploymentTemplate $deploymentTemplate): bool
    {
        return $authUser->can('Delete:DeploymentTemplate');
    }

    public function restore(AuthUser $authUser, DeploymentTemplate $deploymentTemplate): bool
    {
        return $authUser->can('Restore:DeploymentTemplate');
    }

    public function forceDelete(AuthUser $authUser, DeploymentTemplate $deploymentTemplate): bool
    {
        return $authUser->can('ForceDelete:DeploymentTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DeploymentTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DeploymentTemplate');
    }

    public function replicate(AuthUser $authUser, DeploymentTemplate $deploymentTemplate): bool
    {
        return $authUser->can('Replicate:DeploymentTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DeploymentTemplate');
    }

}