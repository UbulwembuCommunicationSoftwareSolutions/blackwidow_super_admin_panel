<?php

namespace App\Support\UserSync;

use App\Models\CustomerUser;
use Illuminate\Support\Carbon;

/**
 * The canonical wire shape for a customer user.
 *
 * Both directions of the Super Admin <-> tenant app sync serialise to and parse
 * from exactly these keys, so there is one contract rather than a different
 * field set per endpoint. The tenant app mirrors this class.
 *
 * Identity is carried by two ids that are both stable and both stored on each
 * side, so neither system ever has to fall back to matching on email:
 *   - super_admin_user_id: this panel's customer_users.id
 *   - cms_user_id:         the tenant CMS's users.id
 */
final class UserSyncPayload
{
    /**
     * Access flags that gate each tenant app, mapped to their subscription_type_id.
     *
     * @var array<string, int>
     */
    public const ACCESS_FLAGS = [
        'console_access' => 1,
        'firearm_access' => 2,
        'responder_access' => 3,
        'reporter_access' => 4,
        'security_access' => 5,
        'driver_access' => 6,
        'survey_access' => 7,
        'time_and_attendance_access' => 9,
        'stock_access' => 10,
    ];

    /**
     * @param  array<string, bool>  $access  Keyed by the flag names in self::ACCESS_FLAGS.
     */
    public function __construct(
        public readonly ?int $superAdminUserId,
        public readonly ?int $cmsUserId,
        public readonly string $email,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $cellphone,
        public readonly array $access,
        public readonly bool $isSystemAdmin,
        public readonly ?Carbon $deleteScheduled,
        public readonly ?Carbon $updatedAt,
    ) {}

    public static function fromCustomerUser(CustomerUser $user): self
    {
        $access = [];
        foreach (array_keys(self::ACCESS_FLAGS) as $flag) {
            $access[$flag] = (bool) $user->{$flag};
        }

        return new self(
            superAdminUserId: $user->id,
            cmsUserId: $user->cms_user_id,
            email: (string) $user->email_address,
            firstName: $user->first_name,
            lastName: $user->last_name,
            cellphone: $user->cellphone,
            access: $access,
            isSystemAdmin: (bool) $user->is_system_admin,
            deleteScheduled: $user->delete_scheduled,
            updatedAt: $user->updated_at,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $access = [];
        foreach (array_keys(self::ACCESS_FLAGS) as $flag) {
            $access[$flag] = array_key_exists($flag, $data)
                ? filter_var($data[$flag], FILTER_VALIDATE_BOOLEAN)
                : false;
        }

        return new self(
            superAdminUserId: isset($data['super_admin_user_id']) ? (int) $data['super_admin_user_id'] : null,
            cmsUserId: isset($data['cms_user_id']) ? (int) $data['cms_user_id'] : null,
            email: (string) ($data['email'] ?? ''),
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            cellphone: $data['cellphone'] ?? null,
            access: $access,
            isSystemAdmin: filter_var($data['is_system_admin'] ?? false, FILTER_VALIDATE_BOOLEAN),
            deleteScheduled: isset($data['delete_scheduled']) ? Carbon::parse($data['delete_scheduled']) : null,
            updatedAt: isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge([
            'super_admin_user_id' => $this->superAdminUserId,
            'cms_user_id' => $this->cmsUserId,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'cellphone' => $this->cellphone,
            'is_system_admin' => $this->isSystemAdmin,
        ], $this->access, [
            'delete_scheduled' => $this->deleteScheduled?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ]);
    }

    public function accessFlag(string $flag): bool
    {
        return $this->access[$flag] ?? false;
    }

    /**
     * Which flag grants access to a given subscription type, if any.
     */
    public static function flagForSubscriptionType(int $subscriptionTypeId): ?string
    {
        $flag = array_search($subscriptionTypeId, self::ACCESS_FLAGS, true);

        return $flag === false ? null : $flag;
    }

    /**
     * Attributes to write onto a CustomerUser. Excludes identity, password and
     * tombstone, which callers handle explicitly.
     *
     * @return array<string, mixed>
     */
    public function toCustomerUserAttributes(): array
    {
        return array_merge([
            'email_address' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'cellphone' => $this->cellphone,
            'is_system_admin' => $this->isSystemAdmin,
        ], $this->access);
    }

    /**
     * Validation rules for the canonical payload, nested under $prefix.
     *
     * @return array<string, string>
     */
    public static function validationRules(string $prefix = 'user'): array
    {
        $key = $prefix === '' ? '' : $prefix.'.';

        $rules = [
            $key.'super_admin_user_id' => 'nullable|integer',
            $key.'cms_user_id' => 'nullable|integer',
            $key.'email' => 'required|email',
            $key.'first_name' => 'nullable|string|max:255',
            $key.'last_name' => 'nullable|string|max:255',
            $key.'cellphone' => 'nullable|string|max:255',
            $key.'is_system_admin' => 'nullable|boolean',
            $key.'delete_scheduled' => 'nullable|date',
            $key.'updated_at' => 'nullable|date',
        ];

        foreach (array_keys(self::ACCESS_FLAGS) as $flag) {
            $rules[$key.$flag] = 'nullable|boolean';
        }

        return $rules;
    }
}
