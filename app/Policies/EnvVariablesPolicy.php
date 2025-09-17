<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\EnvVariables;
use Illuminate\Auth\Access\HandlesAuthorization;

class EnvVariablesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EnvVariables');
    }

    public function view(AuthUser $authUser, EnvVariables $envVariables): bool
    {
        return $authUser->can('View:EnvVariables');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EnvVariables');
    }

    public function update(AuthUser $authUser, EnvVariables $envVariables): bool
    {
        return $authUser->can('Update:EnvVariables');
    }

    public function delete(AuthUser $authUser, EnvVariables $envVariables): bool
    {
        return $authUser->can('Delete:EnvVariables');
    }

    public function restore(AuthUser $authUser, EnvVariables $envVariables): bool
    {
        return $authUser->can('Restore:EnvVariables');
    }

    public function forceDelete(AuthUser $authUser, EnvVariables $envVariables): bool
    {
        return $authUser->can('ForceDelete:EnvVariables');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EnvVariables');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EnvVariables');
    }

    public function replicate(AuthUser $authUser, EnvVariables $envVariables): bool
    {
        return $authUser->can('Replicate:EnvVariables');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EnvVariables');
    }

}