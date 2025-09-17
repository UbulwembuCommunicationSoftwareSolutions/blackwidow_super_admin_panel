<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RequiredEnvVariables;
use Illuminate\Auth\Access\HandlesAuthorization;

class RequiredEnvVariablesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RequiredEnvVariables');
    }

    public function view(AuthUser $authUser, RequiredEnvVariables $requiredEnvVariables): bool
    {
        return $authUser->can('View:RequiredEnvVariables');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RequiredEnvVariables');
    }

    public function update(AuthUser $authUser, RequiredEnvVariables $requiredEnvVariables): bool
    {
        return $authUser->can('Update:RequiredEnvVariables');
    }

    public function delete(AuthUser $authUser, RequiredEnvVariables $requiredEnvVariables): bool
    {
        return $authUser->can('Delete:RequiredEnvVariables');
    }

    public function restore(AuthUser $authUser, RequiredEnvVariables $requiredEnvVariables): bool
    {
        return $authUser->can('Restore:RequiredEnvVariables');
    }

    public function forceDelete(AuthUser $authUser, RequiredEnvVariables $requiredEnvVariables): bool
    {
        return $authUser->can('ForceDelete:RequiredEnvVariables');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RequiredEnvVariables');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RequiredEnvVariables');
    }

    public function replicate(AuthUser $authUser, RequiredEnvVariables $requiredEnvVariables): bool
    {
        return $authUser->can('Replicate:RequiredEnvVariables');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RequiredEnvVariables');
    }

}