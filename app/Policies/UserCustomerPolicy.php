<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserCustomer;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserCustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {

    }

    public function view(User $user, UserCustomer $userCustomer): bool
    {
    }

    public function create(User $user): bool
    {
    }

    public function update(User $user, UserCustomer $userCustomer): bool
    {
    }

    public function delete(User $user, UserCustomer $userCustomer): bool
    {
    }

    public function restore(User $user, UserCustomer $userCustomer): bool
    {
    }

    public function forceDelete(User $user, UserCustomer $userCustomer): bool
    {
    }
}
