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
       return true;
    }

    public function view(User $user, CustomerSubscription $customerSubscription): bool
    {
       return true;
    }

    public function create(User $user): bool
    {
       return true;
    }

    public function update(User $user, CustomerSubscription $customerSubscription): bool
    {
       return true;
    }

    public function delete(User $user, CustomerSubscription $customerSubscription): bool
    {
       return true;
    }

    public function restore(User $user, CustomerSubscription $customerSubscription): bool
    {
       return true;
    }

    public function forceDelete(User $user, CustomerSubscription $customerSubscription): bool
    {
       return true;
    }
}
