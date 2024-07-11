<?php

namespace App\Policies;

use App\Models\SubscriptionType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubscriptionTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SubscriptionType $subscriptionType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SubscriptionType $subscriptionType): bool
    {
        return true;
    }

    public function delete(User $user, SubscriptionType $subscriptionType): bool
    {
        return true;
    }

    public function restore(User $user, SubscriptionType $subscriptionType): bool
    {
        return true;
    }

    public function forceDelete(User $user, SubscriptionType $subscriptionType): bool
    {
        return true;
    }
}
