<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CustomerSubscription;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerSubscriptionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CustomerSubscription');
    }

    public function view(AuthUser $authUser, CustomerSubscription $customerSubscription): bool
    {
        return $authUser->can('View:CustomerSubscription');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CustomerSubscription');
    }

    public function update(AuthUser $authUser, CustomerSubscription $customerSubscription): bool
    {
        return $authUser->can('Update:CustomerSubscription');
    }

    public function delete(AuthUser $authUser, CustomerSubscription $customerSubscription): bool
    {
        return $authUser->can('Delete:CustomerSubscription');
    }

    public function restore(AuthUser $authUser, CustomerSubscription $customerSubscription): bool
    {
        return $authUser->can('Restore:CustomerSubscription');
    }

    public function forceDelete(AuthUser $authUser, CustomerSubscription $customerSubscription): bool
    {
        return $authUser->can('ForceDelete:CustomerSubscription');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CustomerSubscription');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CustomerSubscription');
    }

    public function replicate(AuthUser $authUser, CustomerSubscription $customerSubscription): bool
    {
        return $authUser->can('Replicate:CustomerSubscription');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CustomerSubscription');
    }

}