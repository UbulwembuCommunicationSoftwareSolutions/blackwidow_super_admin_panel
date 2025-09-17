<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\UserCustomer;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserCustomerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserCustomer');
    }

    public function view(AuthUser $authUser, UserCustomer $userCustomer): bool
    {
        return $authUser->can('View:UserCustomer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserCustomer');
    }

    public function update(AuthUser $authUser, UserCustomer $userCustomer): bool
    {
        return $authUser->can('Update:UserCustomer');
    }

    public function delete(AuthUser $authUser, UserCustomer $userCustomer): bool
    {
        return $authUser->can('Delete:UserCustomer');
    }

    public function restore(AuthUser $authUser, UserCustomer $userCustomer): bool
    {
        return $authUser->can('Restore:UserCustomer');
    }

    public function forceDelete(AuthUser $authUser, UserCustomer $userCustomer): bool
    {
        return $authUser->can('ForceDelete:UserCustomer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:UserCustomer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:UserCustomer');
    }

    public function replicate(AuthUser $authUser, UserCustomer $userCustomer): bool
    {
        return $authUser->can('Replicate:UserCustomer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:UserCustomer');
    }

}