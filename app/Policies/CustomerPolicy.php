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
        return false;
    }

    public function view(User $user, Customer $customer)
    {
        return false;
    }

    public function create(User $user)
    {
        return false;
    }

    public function update(User $user, Customer $customer)
    {
        return false;
    }

    public function delete(User $user, Customer $customer)
    {
        return false;
    }

    public function restore(User $user, Customer $customer)
    {
        return false;
    }

    public function forceDelete(User $user, Customer $customer)
    {
        return false;
    }
}
