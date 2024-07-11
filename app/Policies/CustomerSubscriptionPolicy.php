<?php

namespace App\Policies;

use App\Models\CustomerSubscription;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerSubscriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, CustomerSubscription $customerSubscription): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CustomerSubscription $customerSubscription): bool
    {
        return false;
    }

    public function delete(User $user, CustomerSubscription $customerSubscription): bool
    {
        return false;
    }

    public function restore(User $user, CustomerSubscription $customerSubscription): bool
    {
        return false;
    }

    public function forceDelete(User $user, CustomerSubscription $customerSubscription): bool
    {
        return false;
    }
}
