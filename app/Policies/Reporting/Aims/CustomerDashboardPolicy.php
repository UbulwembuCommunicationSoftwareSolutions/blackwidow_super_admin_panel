<?php

declare(strict_types=1);

namespace App\Policies\Reporting\Aims;

use App\Models\Reporting\Aims\CustomerDashboard;
use App\Support\CustomerAdminAccess;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CustomerDashboardPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        if (CustomerAdminAccess::isCustomerAdmin($authUser)) {
            return true;
        }

        return $authUser->can('ViewAny:Dashboard');
    }

    public function view(AuthUser $authUser, CustomerDashboard $customerDashboard): bool
    {
        return $customerDashboard->allowsActor($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Dashboard');
    }

    public function update(AuthUser $authUser, CustomerDashboard $customerDashboard): bool
    {
        if (CustomerAdminAccess::isCustomerAdmin($authUser)) {
            return CustomerAdminAccess::allows($authUser, $customerDashboard->customer_id);
        }

        return $authUser->can('Update:Dashboard');
    }

    public function delete(AuthUser $authUser, CustomerDashboard $customerDashboard): bool
    {
        return $authUser->can('Delete:Dashboard');
    }
}
