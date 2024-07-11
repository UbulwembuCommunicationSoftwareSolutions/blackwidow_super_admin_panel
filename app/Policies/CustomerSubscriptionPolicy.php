<?php

namespace App\Policies;

use App\Models\CustomerSubscription;
use AppModels\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerSubscriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {

    }

    public function view(User $user, CustomerSubscription $customerSubscription): bool
    {
    }

    public function create(User $user): bool
    {
    }

    public function update(User $user, CustomerSubscription $customerSubscription): bool
    {
    }

    public function delete(User $user, CustomerSubscription $customerSubscription): bool
    {
    }

    public function restore(User $user, CustomerSubscription $customerSubscription): bool
    {
    }

    public function forceDelete(User $user, CustomerSubscription $customerSubscription): bool
    {
    }
}
