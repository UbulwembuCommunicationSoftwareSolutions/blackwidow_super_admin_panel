<?php

namespace App\Services\UserFieldSync;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\CustomerUserField;
use App\Models\CustomerUserFieldValue;
use App\Support\UserFieldSync\UserFieldSyncOutcome;
use App\Support\UserFieldSync\UserFieldSyncPayload;
use App\Support\UserFieldSync\UserFieldValueSyncPayload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomerUserFieldSyncService
{
    /**
     * @return array{outcome: UserFieldSyncOutcome, payload: UserFieldSyncPayload}
     */
    public function upsertDefinition(Customer $customer, UserFieldSyncPayload $incoming): array
    {
        $field = $this->locateField($customer, $incoming);

        if ($field !== null) {
            $local = UserFieldSyncPayload::fromModel($field);

            if ($incoming->contentEquals($local)) {
                return ['outcome' => UserFieldSyncOutcome::Unchanged, 'payload' => $local];
            }

            $remoteTs = $incoming->updatedAt ?? now();
            if ($field->updated_at && $remoteTs->lt($field->updated_at)) {
                return ['outcome' => UserFieldSyncOutcome::Stale, 'payload' => $local];
            }

            $this->applyDefinitionAttributes($field, $incoming, $remoteTs);
            $field->skipSync = true;
            $field->skip_sync = true;
            $field->save();

            return [
                'outcome' => UserFieldSyncOutcome::Updated,
                'payload' => UserFieldSyncPayload::fromModel($field->fresh()),
            ];
        }

        $field = new CustomerUserField([
            'customer_id' => $customer->id,
        ]);
        $this->applyDefinitionAttributes($field, $incoming, $incoming->updatedAt ?? now());
        $field->skipSync = true;
        $field->skip_sync = true;
        $field->save();

        return [
            'outcome' => UserFieldSyncOutcome::Created,
            'payload' => UserFieldSyncPayload::fromModel($field->fresh()),
        ];
    }

    /**
     * @return array{outcome: UserFieldSyncOutcome, payload: UserFieldSyncPayload}
     */
    public function archiveDefinition(Customer $customer, UserFieldSyncPayload $incoming): array
    {
        $field = $this->locateField($customer, $incoming);
        if ($field === null) {
            return [
                'outcome' => UserFieldSyncOutcome::Unresolved,
                'payload' => $incoming,
            ];
        }

        if ($field->trashed()) {
            return [
                'outcome' => UserFieldSyncOutcome::Unchanged,
                'payload' => UserFieldSyncPayload::fromModel($field),
            ];
        }

        $remoteTs = $incoming->updatedAt ?? now();
        if ($field->updated_at && $remoteTs->lt($field->updated_at)) {
            return [
                'outcome' => UserFieldSyncOutcome::Stale,
                'payload' => UserFieldSyncPayload::fromModel($field),
            ];
        }

        $field->skipSync = true;
        $field->skip_sync = true;
        $field->delete();

        return [
            'outcome' => UserFieldSyncOutcome::Archived,
            'payload' => UserFieldSyncPayload::fromModel(
                CustomerUserField::withTrashed()->find($field->id) ?? $field
            ),
        ];
    }

    /**
     * @return array{outcome: UserFieldSyncOutcome, payload: UserFieldSyncPayload}
     */
    public function restoreDefinition(Customer $customer, UserFieldSyncPayload $incoming): array
    {
        $field = $this->locateField($customer, $incoming, withTrashed: true);
        if ($field === null) {
            return [
                'outcome' => UserFieldSyncOutcome::Unresolved,
                'payload' => $incoming,
            ];
        }

        if (! $field->trashed()) {
            return [
                'outcome' => UserFieldSyncOutcome::Unchanged,
                'payload' => UserFieldSyncPayload::fromModel($field),
            ];
        }

        $field->skipSync = true;
        $field->skip_sync = true;
        $field->restore();

        return [
            'outcome' => UserFieldSyncOutcome::Restored,
            'payload' => UserFieldSyncPayload::fromModel($field->fresh()),
        ];
    }

    /**
     * @return array{outcome: UserFieldSyncOutcome, payload: UserFieldValueSyncPayload}
     */
    public function upsertValues(Customer $customer, UserFieldValueSyncPayload $incoming): array
    {
        if ($incoming->superAdminUserId === null) {
            return [
                'outcome' => UserFieldSyncOutcome::Unresolved,
                'payload' => $incoming,
            ];
        }

        $user = CustomerUser::query()
            ->where('customer_id', $customer->id)
            ->where('id', $incoming->superAdminUserId)
            ->first();

        if ($user === null) {
            return [
                'outcome' => UserFieldSyncOutcome::Unresolved,
                'payload' => $incoming,
            ];
        }

        $fieldsByName = CustomerUserField::query()
            ->where('customer_id', $customer->id)
            ->get()
            ->keyBy('name');

        $changed = false;
        $cleared = false;
        $staleOnly = true;

        foreach ($incoming->values as $row) {
            $field = $fieldsByName->get($row['name']);
            if ($field === null) {
                return [
                    'outcome' => UserFieldSyncOutcome::Unresolved,
                    'payload' => $incoming,
                ];
            }

            $existing = CustomerUserFieldValue::query()
                ->where('customer_user_id', $user->id)
                ->where('customer_user_field_id', $field->id)
                ->first();

            $remoteTs = $row['updated_at'] ?? now();
            if ($existing !== null && $existing->updated_at && $remoteTs->lt($existing->updated_at)) {
                continue;
            }

            $staleOnly = false;
            $incomingValue = $row['value'];

            if ($incomingValue === null) {
                if ($existing !== null) {
                    $existing->skipSync = true;
                    $existing->skip_sync = true;
                    $existing->delete();
                    $changed = true;
                    $cleared = true;
                }

                continue;
            }

            if ($existing !== null && (string) $existing->value === (string) $incomingValue) {
                continue;
            }

            if ($existing === null) {
                $existing = new CustomerUserFieldValue([
                    'customer_user_id' => $user->id,
                    'customer_user_field_id' => $field->id,
                ]);
            }

            $existing->value = $incomingValue;
            $existing->skipSync = true;
            $existing->skip_sync = true;
            $existing->updated_at = $remoteTs instanceof Carbon ? $remoteTs : Carbon::parse($remoteTs);
            $existing->save();
            $changed = true;
        }

        $freshRows = CustomerUserFieldValue::query()
            ->where('customer_user_id', $user->id)
            ->with('field')
            ->get();

        $payload = UserFieldValueSyncPayload::fromUser($user, $freshRows);

        if (! $changed && $staleOnly && $incoming->values !== []) {
            return ['outcome' => UserFieldSyncOutcome::Stale, 'payload' => $payload];
        }

        if (! $changed) {
            return ['outcome' => UserFieldSyncOutcome::Unchanged, 'payload' => $payload];
        }

        if ($cleared && collect($incoming->values)->every(fn (array $row): bool => $row['value'] === null)) {
            return ['outcome' => UserFieldSyncOutcome::Cleared, 'payload' => $payload];
        }

        $hadAny = $freshRows->isNotEmpty() || $cleared;

        return [
            'outcome' => $hadAny ? UserFieldSyncOutcome::Updated : UserFieldSyncOutcome::Created,
            'payload' => $payload,
        ];
    }

    /**
     * @return Collection<int, UserFieldSyncPayload>
     */
    public function listDefinitions(Customer $customer): Collection
    {
        return CustomerUserField::query()
            ->where('customer_id', $customer->id)
            ->withTrashed()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (CustomerUserField $field): UserFieldSyncPayload => UserFieldSyncPayload::fromModel($field));
    }

    /**
     * @return Collection<int, UserFieldValueSyncPayload>
     */
    public function listValues(Customer $customer): Collection
    {
        $users = CustomerUser::query()
            ->where('customer_id', $customer->id)
            ->whereHas('fieldValues')
            ->with(['fieldValues.field'])
            ->get();

        return $users->map(function (CustomerUser $user): UserFieldValueSyncPayload {
            return UserFieldValueSyncPayload::fromUser($user, $user->fieldValues);
        });
    }

    public function customerFromSubscription(CustomerSubscription $subscription): Customer
    {
        $subscription->loadMissing('customer');

        if ($subscription->customer === null) {
            throw new \RuntimeException('Subscription has no customer.');
        }

        return $subscription->customer;
    }

    private function locateField(
        Customer $customer,
        UserFieldSyncPayload $incoming,
        bool $withTrashed = false,
    ): ?CustomerUserField {
        $query = CustomerUserField::query()->where('customer_id', $customer->id);
        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($incoming->superAdminUserFieldId !== null) {
            $byId = (clone $query)->where('id', $incoming->superAdminUserFieldId)->first();
            if ($byId !== null) {
                return $byId;
            }
        }

        if ($incoming->name !== '') {
            return (clone $query)->where('name', $incoming->name)->first();
        }

        return null;
    }

    private function applyDefinitionAttributes(
        CustomerUserField $field,
        UserFieldSyncPayload $incoming,
        mixed $remoteTs,
    ): void {
        $field->name = $incoming->name;
        $field->label = $incoming->label;
        $field->type = $incoming->type;
        $field->rules = $incoming->rules;
        $field->options = $incoming->options;
        $field->sort_order = $incoming->sortOrder;
        $field->active = $incoming->active;
        $field->updated_at = $remoteTs instanceof Carbon ? $remoteTs : Carbon::parse($remoteTs);
    }
}
