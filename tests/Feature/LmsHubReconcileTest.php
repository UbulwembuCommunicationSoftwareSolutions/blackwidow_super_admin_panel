<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
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

function hubReconcileSetup(string $lmsUrl = 'https://lms-hub.example.test'): array
{
    SubscriptionType::factory()->create([
        'id' => SubscriptionType::LMS_TYPE_ID,
        'name' => 'LMS',
    ]);

    $customer = Customer::factory()->create([
        'token' => 'customer-with-lms-sub',
        'company_name' => 'Acme Corp',
    ]);

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => SubscriptionType::LMS_TYPE_ID,
        'url' => $lmsUrl,
    ]);

    return compact('customer', 'lmsUrl');
}

it('lists lms-eligible users on the hub endpoint', function () {
    ['customer' => $customer, 'lmsUrl' => $lmsUrl] = hubReconcileSetup();

    CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'lms-user@example.test',
        'lms_access' => true,
    ]);

    CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'no-lms@example.test',
        'lms_access' => false,
        'is_system_admin' => false,
    ]);

    $response = $this->withToken('lms-shared-sync-token')
        ->getJson('/api/v1/sync/users/hub?app_url='.urlencode($lmsUrl));

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $emails = collect($response->json('users'))->pluck('email');

    expect($emails)->toContain('lms-user@example.test')
        ->and($emails)->not->toContain('no-lms@example.test');
});

it('lists customer default branding on the hub endpoint', function () {
    ['customer' => $customer, 'lmsUrl' => $lmsUrl] = hubReconcileSetup();

    $response = $this->withToken('lms-shared-sync-token')
        ->getJson('/api/v1/sync/branding/hub?app_url='.urlencode($lmsUrl));

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $row = collect($response->json('data'))->firstWhere('super_admin_customer_id', $customer->id);

    expect($row)->not->toBeNull()
        ->and($row['branding'])->toHaveCount(3);
});

it('accepts lms sync token for branding upsert onto customer defaults', function () {
    ['customer' => $customer, 'lmsUrl' => $lmsUrl] = hubReconcileSetup();

    $response = $this->withToken('lms-shared-sync-token')
        ->postJson('/api/v1/sync/branding', [
            'app_url' => $lmsUrl,
            'origin' => 'lms',
            'branding' => [
                'super_admin_customer_id' => $customer->id,
                'slot' => 'login_logo',
                'url' => null,
                'checksum' => null,
                'cleared' => true,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true);
});
