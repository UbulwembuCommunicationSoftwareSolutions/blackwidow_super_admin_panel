<?php

declare(strict_types=1);

namespace App\Policies\Reporting\Aims;

use App\Models\Reporting\Aims\Dashboard;
use App\Support\CustomerAdminAccess;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DashboardPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        if (CustomerAdminAccess::isCustomerAdmin($authUser)) {
            return true;
        }

        return $authUser->can('ViewAny:Dashboard');
    }

    public function view(AuthUser $authUser, Dashboard $dashboard): bool
    {
        if (CustomerAdminAccess::isCustomerAdmin($authUser)) {
            return true;
        }

        return $authUser->can('View:Dashboard');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Dashboard');
    }

    public function update(AuthUser $authUser, Dashboard $dashboard): bool
    {
        return $authUser->can('Update:Dashboard');
    }

    public function delete(AuthUser $authUser, Dashboard $dashboard): bool
    {
        return $authUser->can('Delete:Dashboard');
    }
}
