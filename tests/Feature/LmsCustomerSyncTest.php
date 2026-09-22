<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Support\CustomerSync\CustomerSyncPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    config([
        'services.lms.sync_token' => 'lms-shared-sync-token',
        'customer_sync.lms_subscription_type_id' => SubscriptionType::LMS_TYPE_ID,
    ]);
});

function lmsHubSetup(string $lmsUrl = 'https://lms-hub.example.test'): array
{
    SubscriptionType::factory()->create([
        'id' => SubscriptionType::LMS_TYPE_ID,
        'name' => 'LMS',
    ]);

    $customer = Customer::factory()->create([
        'token' => 'customer-with-lms-sub',
        'company_name' => 'Acme Corp',
        'max_users' => 50,
        'uuid' => '550e8400-e29b-41d4-a716-446655440000',
    ]);

    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => SubscriptionType::LMS_TYPE_ID,
        'url' => $lmsUrl,
    ]);

    return compact('customer', 'subscription', 'lmsUrl');
}

it('rejects customer sync list without bearer token', function () {
    ['lmsUrl' => $lmsUrl] = lmsHubSetup();

    $this->getJson('/api/v1/sync/customers?app_url='.urlencode($lmsUrl))
        ->assertUnauthorized();
});

it('rejects customer sync list with wrong bearer token', function () {
    ['lmsUrl' => $lmsUrl] = lmsHubSetup();

    $this->withToken('wrong-token')
        ->getJson('/api/v1/sync/customers?app_url='.urlencode($lmsUrl))
        ->assertUnauthorized();
});

it('rejects customer sync list when app_url is not a registered LMS hub', function () {
    lmsHubSetup();

    $this->withToken('lms-shared-sync-token')
        ->getJson('/api/v1/sync/customers?app_url='.urlencode('https://unknown-lms.example.test'))
        ->assertUnauthorized();
});

it('lists all active customers for the LMS hub with shared sync token', function () {
    ['customer' => $acmeCustomer, 'lmsUrl' => $lmsUrl] = lmsHubSetup();

    Customer::factory()->create([
        'company_name' => 'Beta Ltd',
        'max_users' => 10,
    ]);

    $response = $this->withToken('lms-shared-sync-token')
        ->getJson('/api/v1/sync/customers?app_url='.urlencode($lmsUrl));

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data');

    $acmeRow = collect($response->json('data'))->firstWhere('super_admin_customer_id', $acmeCustomer->id);
    $betaRow = collect($response->json('data'))->firstWhere('company_name', 'Beta Ltd');

    expect($acmeRow)->not->toBeNull()
        ->and($acmeRow['slug'])->toBe('acme-corp')
        ->and($acmeRow['max_users'])->toBe(50)
        ->and($acmeRow['is_active'])->toBeTrue()
        ->and($acmeRow['sync_hash'])->toBe(
            CustomerSyncPayload::hashForWireShape([
                'super_admin_customer_id' => $acmeCustomer->id,
                'uuid' => $acmeRow['uuid'],
                'company_name' => 'Acme Corp',
                'slug' => 'acme-corp',
                'max_users' => 50,
                'is_active' => true,
            ])
        )
        ->and($betaRow)->not->toBeNull();
});

it('allows customer sync list with a customer token tied to the LMS hub url', function () {
    ['customer' => $customer, 'lmsUrl' => $lmsUrl] = lmsHubSetup();

    $this->withToken((string) $customer->token)
        ->getJson('/api/v1/sync/customers?app_url='.urlencode($lmsUrl))
        ->assertSuccessful()
        ->assertJsonPath('success', true);
});

it('uses lms as the url slug for subscription type 12', function () {
    expect(SubscriptionType::urlSlugFor(SubscriptionType::LMS_TYPE_ID, 'LMS'))->toBe('lms');
});
