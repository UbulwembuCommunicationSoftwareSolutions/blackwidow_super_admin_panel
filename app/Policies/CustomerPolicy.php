<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Customer $customer)
    {
        return true;
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user, Customer $customer)
    {
        return true;
    }

    public function delete(User $user, Customer $customer)
    {
        return true;
    }

    public function restore(User $user, Customer $customer)
    {
        return true;
    }

    public function forceDelete(User $user, Customer $customer)
    {
        return true;
    }
}
