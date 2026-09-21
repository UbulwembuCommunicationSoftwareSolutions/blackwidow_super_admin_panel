<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerUser;
use App\Support\CustomerAdminAccess;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CustomerUserPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        if (CustomerAdminAccess::isCustomerAdmin($authUser)) {
            return true;
        }

        return $authUser->can('ViewAny:CustomerUser');
    }

    public function view(AuthUser $authUser, CustomerUser $customerUser): bool
    {
        if (CustomerAdminAccess::isCustomerAdmin($authUser)) {
            return CustomerAdminAccess::allows($authUser, $customerUser->customer_id);
        }

        return $authUser->can('View:CustomerUser');
    }

    public function create(AuthUser $authUser): bool
    {
        if (CustomerAdminAccess::isCustomerAdmin($authUser)) {
            if (request()->boolean('is_system_admin')) {
                return false;
            }

            return CustomerAdminAccess::allows($authUser, request()->input('customer_id'));
        }

        return $authUser->can('Create:CustomerUser');
    }

    public function update(AuthUser $authUser, CustomerUser $customerUser): bool
    {
        if (CustomerAdminAccess::isCustomerAdmin($authUser)) {
            if (! CustomerAdminAccess::allows($authUser, $customerUser->customer_id)) {
                return false;
            }

            if ($this->customerAdminIsChangingSystemAdmin($customerUser)) {
                return false;
            }

            $requestedCustomerId = request()->input('customer_id');
            if ($requestedCustomerId !== null && (int) $requestedCustomerId !== (int) $customerUser->customer_id) {
                return false;
            }

            return true;
        }

        return $authUser->can('Update:CustomerUser');
    }

    public function delete(AuthUser $authUser, CustomerUser $customerUser): bool
    {
        if (CustomerAdminAccess::isCustomerAdmin($authUser)) {
            return CustomerAdminAccess::allows($authUser, $customerUser->customer_id);
        }

        return $authUser->can('Delete:CustomerUser');
    }

    public function restore(AuthUser $authUser, CustomerUser $customerUser): bool
    {
        if (CustomerAdminAccess::isCustomerAdmin($authUser)) {
            return CustomerAdminAccess::allows($authUser, $customerUser->customer_id);
        }

        return $authUser->can('Restore:CustomerUser');
    }

    public function forceDelete(AuthUser $authUser, CustomerUser $customerUser): bool
    {
        return $authUser->can('ForceDelete:CustomerUser');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CustomerUser');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CustomerUser');
    }

    public function replicate(AuthUser $authUser, CustomerUser $customerUser): bool
    {
        return $authUser->can('Replicate:CustomerUser');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CustomerUser');
    }

    private function customerAdminIsChangingSystemAdmin(CustomerUser $customerUser): bool
    {
        if (! request()->exists('is_system_admin')) {
            return false;
        }

        return request()->boolean('is_system_admin') !== (bool) $customerUser->is_system_admin;
    }
}
