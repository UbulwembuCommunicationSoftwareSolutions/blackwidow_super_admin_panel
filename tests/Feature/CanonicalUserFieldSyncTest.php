<?php

use App\Jobs\PushCustomerUserFieldToTenantsJob;
use App\Jobs\PushCustomerUserFieldValuesToTenantsJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\CustomerUserField;
use App\Models\CustomerUserFieldValue;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Http::fake(['*' => Http::response(['success' => true, 'outcome' => 'updated'], 200)]);
    config(['user_field_sync.enabled' => true]);
});

function userFieldTenant(): array
{
    $customer = Customer::factory()->create(['token' => 'user-field-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-fields.example.test',
    ]);

    return compact('customer', 'subscription');
}

function postUserField(string $path, array $body)
{
    return test()->withToken('user-field-token')->postJson('/api/v1/sync/user-fields'.$path, $body);
}

function postUserFieldValues(array $body)
{
    return test()->withToken('user-field-token')->postJson('/api/v1/sync/user-field-values', $body);
}

it('creates a customer user field from a tenant', function () {
    ['subscription' => $subscription] = userFieldTenant();

    $response = postUserField('', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'user_field' => [
            'name' => 'id_number',
            'label' => 'ID Number',
            'type' => 'text',
            'rules' => 'nullable|digits:13',
            'sort_order' => 1,
            'active' => true,
            'updated_at' => now()->toIso8601String(),
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('outcome', 'created')
        ->assertJsonPath('user_field.name', 'id_number');

    $field = CustomerUserField::query()->first();
    expect($field)->not->toBeNull()
        ->and($field->label)->toBe('ID Number')
        ->and($response->json('user_field.super_admin_user_field_id'))->toBe($field->id);
});

it('rejects a stale definition update', function () {
    ['subscription' => $subscription, 'customer' => $customer] = userFieldTenant();

    $field = CustomerUserField::query()->create([
        'customer_id' => $customer->id,
        'name' => 'id_number',
        'label' => 'ID Number',
        'type' => 'text',
        'sort_order' => 0,
        'active' => true,
        'updated_at' => now(),
    ]);

    $response = postUserField('', [
        'app_url' => $subscription->url,
        'user_field' => [
            'super_admin_user_field_id' => $field->id,
            'name' => 'id_number',
            'label' => 'Stale Label',
            'type' => 'text',
            'updated_at' => now()->subHour()->toIso8601String(),
        ],
    ]);

    $response->assertOk()->assertJsonPath('outcome', 'stale');
    expect($field->fresh()->label)->toBe('ID Number');
});

it('archives and restores a definition', function () {
    ['subscription' => $subscription, 'customer' => $customer] = userFieldTenant();

    $field = CustomerUserField::query()->create([
        'customer_id' => $customer->id,
        'name' => 'passport_number',
        'label' => 'Passport',
        'type' => 'text',
        'active' => true,
    ]);

    postUserField('/archive', [
        'app_url' => $subscription->url,
        'user_field' => [
            'super_admin_user_field_id' => $field->id,
            'name' => 'passport_number',
            'label' => 'Passport',
            'type' => 'text',
            'updated_at' => now()->toIso8601String(),
        ],
    ])->assertOk()->assertJsonPath('outcome', 'archived');

    expect(CustomerUserField::withTrashed()->find($field->id)->trashed())->toBeTrue();

    postUserField('/restore', [
        'app_url' => $subscription->url,
        'user_field' => [
            'super_admin_user_field_id' => $field->id,
            'name' => 'passport_number',
            'label' => 'Passport',
            'type' => 'text',
        ],
    ])->assertOk()->assertJsonPath('outcome', 'restored');

    expect(CustomerUserField::find($field->id))->not->toBeNull();
});

it('upserts and clears user field values', function () {
    ['subscription' => $subscription, 'customer' => $customer] = userFieldTenant();

    $user = CustomerUser::factory()->create(['customer_id' => $customer->id]);
    $field = CustomerUserField::query()->create([
        'customer_id' => $customer->id,
        'name' => 'id_number',
        'label' => 'ID Number',
        'type' => 'text',
        'active' => true,
    ]);

    postUserFieldValues([
        'app_url' => $subscription->url,
        'user_field_values' => [
            'super_admin_user_id' => $user->id,
            'values' => [
                [
                    'name' => 'id_number',
                    'value' => '9107283832323',
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
        ],
    ])->assertSuccessful()->assertJsonPath('outcome', 'updated');

    expect(CustomerUserFieldValue::query()->where('customer_user_id', $user->id)->value('value'))
        ->toBe('9107283832323');

    postUserFieldValues([
        'app_url' => $subscription->url,
        'user_field_values' => [
            'super_admin_user_id' => $user->id,
            'values' => [
                [
                    'name' => 'id_number',
                    'value' => null,
                    'updated_at' => now()->addMinute()->toIso8601String(),
                ],
            ],
        ],
    ])->assertOk()->assertJsonPath('outcome', 'cleared');

    expect(CustomerUserFieldValue::query()->where('customer_user_id', $user->id)->exists())->toBeFalse();
});

it('returns unresolved when the hub user does not exist', function () {
    ['subscription' => $subscription, 'customer' => $customer] = userFieldTenant();

    CustomerUserField::query()->create([
        'customer_id' => $customer->id,
        'name' => 'id_number',
        'label' => 'ID Number',
        'type' => 'text',
        'active' => true,
    ]);

    postUserFieldValues([
        'app_url' => $subscription->url,
        'user_field_values' => [
            'super_admin_user_id' => 99999,
            'values' => [
                ['name' => 'id_number', 'value' => 'x', 'updated_at' => now()->toIso8601String()],
            ],
        ],
    ])->assertOk()->assertJsonPath('outcome', 'unresolved');
});

it('lists definitions for reconcile and hub index', function () {
    ['subscription' => $subscription, 'customer' => $customer] = userFieldTenant();

    CustomerUserField::query()->create([
        'customer_id' => $customer->id,
        'name' => 'id_number',
        'label' => 'ID Number',
        'type' => 'text',
        'active' => true,
    ]);

    test()->withToken('user-field-token')
        ->getJson('/api/v1/sync/user-fields?app_url='.urlencode($subscription->url))
        ->assertOk()
        ->assertJsonPath('user_fields.0.name', 'id_number');
});

it('does not echo a push job when applying an inbound definition', function () {
    ['subscription' => $subscription] = userFieldTenant();

    postUserField('', [
        'app_url' => $subscription->url,
        'user_field' => [
            'name' => 'driver_licence_nr',
            'label' => 'Driver Licence',
            'type' => 'text',
            'updated_at' => now()->toIso8601String(),
        ],
    ])->assertCreated();

    Queue::assertNotPushed(PushCustomerUserFieldToTenantsJob::class);
});

it('does not echo a push job when applying inbound values', function () {
    ['subscription' => $subscription, 'customer' => $customer] = userFieldTenant();

    $user = CustomerUser::factory()->create(['customer_id' => $customer->id]);
    CustomerUserField::query()->create([
        'customer_id' => $customer->id,
        'name' => 'id_number',
        'label' => 'ID Number',
        'type' => 'text',
        'active' => true,
    ]);

    postUserFieldValues([
        'app_url' => $subscription->url,
        'user_field_values' => [
            'super_admin_user_id' => $user->id,
            'values' => [
                ['name' => 'id_number', 'value' => '1', 'updated_at' => now()->toIso8601String()],
            ],
        ],
    ])->assertSuccessful();

    Queue::assertNotPushed(PushCustomerUserFieldValuesToTenantsJob::class);
});
