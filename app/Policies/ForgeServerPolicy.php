<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ForgeServer;
use Illuminate\Auth\Access\HandlesAuthorization;

class ForgeServerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ForgeServer');
    }

    public function view(AuthUser $authUser, ForgeServer $forgeServer): bool
    {
        return $authUser->can('View:ForgeServer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ForgeServer');
    }

    public function update(AuthUser $authUser, ForgeServer $forgeServer): bool
    {
        return $authUser->can('Update:ForgeServer');
    }

    public function delete(AuthUser $authUser, ForgeServer $forgeServer): bool
    {
        return $authUser->can('Delete:ForgeServer');
    }

    public function restore(AuthUser $authUser, ForgeServer $forgeServer): bool
    {
        return $authUser->can('Restore:ForgeServer');
    }

    public function forceDelete(AuthUser $authUser, ForgeServer $forgeServer): bool
    {
        return $authUser->can('ForceDelete:ForgeServer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ForgeServer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ForgeServer');
    }

    public function replicate(AuthUser $authUser, ForgeServer $forgeServer): bool
    {
        return $authUser->can('Replicate:ForgeServer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ForgeServer');
    }

}