<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CustomerSubscription;
use App\Support\UserSync\UserSyncPayload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for the canonical tenant sync endpoints.
 *
 * Authentication and tenant resolution are already done by the customer.bearer
 * middleware, which stashes the resolved subscription on the request.
 */
abstract class TenantSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->subscription() !== null;
    }

    public function subscription(): ?CustomerSubscription
    {
        $subscription = $this->attributes->get('customer_subscription');

        return $subscription instanceof CustomerSubscription ? $subscription : null;
    }

    public function payload(): UserSyncPayload
    {
        return UserSyncPayload::fromArray((array) $this->validated('user', []));
    }
}
