<?php

namespace App\Support\UserSync;

use App\Models\CustomerUser;
use Illuminate\Support\Collection;

/**
 * The customer_users rows that share one email inside one customer, plus the
 * decision of how to collapse them into a single row.
 */
final class DuplicateEmailGroup
{
    /**
     * @param  CustomerUser  $keep  The survivor.
     * @param  Collection<int, CustomerUser>  $discard  Ranked, least preferred last.
     * @param  array<string, mixed>  $updates  Attributes the survivor inherits from the discarded rows.
     */
    public function __construct(
        public readonly int $customerId,
        public readonly string $email,
        public readonly CustomerUser $keep,
        public readonly Collection $discard,
        public readonly array $updates,
    ) {}

    /**
     * @return Collection<int, CustomerUser>
     */
    public function rows(): Collection
    {
        return collect([$this->keep])->concat($this->discard);
    }

    public function contains(int $customerUserId): bool
    {
        return $this->rows()->contains(fn (CustomerUser $user) => $user->id === $customerUserId);
    }

    /**
     * More than one row is already linked to a tenant user, so collapsing them
     * silently would leave a live tenant account pointing at the survivor of a
     * different person. A human has to say which link is the real one.
     */
    public function hasCompetingTenantLinks(): bool
    {
        return $this->rows()->whereNotNull('cms_user_id')->count() > 1;
    }
}
