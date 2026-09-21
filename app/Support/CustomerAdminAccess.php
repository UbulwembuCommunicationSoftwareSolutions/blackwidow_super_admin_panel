<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CustomerUser;
use Illuminate\Contracts\Auth\Authenticatable;

class CustomerAdminAccess
{
    /**
     * Shield-style abilities the Reseller Console already checks.
     * Customer admins do not receive these as Spatie permissions.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'View:Customer',
        'ViewAny:CustomerUser',
        'View:CustomerUser',
        'Create:CustomerUser',
        'Update:CustomerUser',
        'Delete:CustomerUser',
        'Restore:CustomerUser',
        'ViewAny:CustomerSubscription',
        'View:CustomerSubscription',
    ];

    public static function isCustomerAdmin(?Authenticatable $user): bool
    {
        return $user instanceof CustomerUser && (bool) $user->is_system_admin;
    }

    public static function customerId(?Authenticatable $user): ?int
    {
        if (! self::isCustomerAdmin($user)) {
            return null;
        }

        return (int) $user->customer_id;
    }

    /**
     * Staff are unrestricted. A customer admin may only touch their own customer.
     */
    public static function allows(?Authenticatable $user, int|string|null $customerId): bool
    {
        if (! self::isCustomerAdmin($user)) {
            return true;
        }

        return $customerId !== null && (int) $customerId === self::customerId($user);
    }

    /**
     * @return list<string>
     */
    public static function permissions(): array
    {
        return self::PERMISSIONS;
    }
}
