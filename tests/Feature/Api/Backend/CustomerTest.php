<?php

use App\Jobs\SyncCustomerEnvToSubscriptionsJob;
use App\Models\Customer;
use App\Models\CustomerUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::fake();
    Queue::fake();
});

function assertCustomerHasNoSecrets(?array $payload): void
{
    expect($payload)->toBeArray();
    foreach ([
        'token',
        'google_api_key',
        's3_endpoint',
        's3_key',
        's3_secret',
        's3_region',
        's3_bucket',
        's3_use_path_style_endpoint',
        'mail_password',
    ] as $key) {
        expect($payload)->not->toHaveKey($key);
    }
}

it('rejects customers without a token', function () {
    $this->getJson('/api/backend/customers')->assertUnauthorized();
});

it('forbids customers without Shield permissions', function () {
    actingAsBackendForbidden();

    $this->getJson('/api/backend/customers')->assertForbidden();
});

it('returns 404 for a missing customer', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/customers/999999')->assertNotFound();
});

it('validates customer create', function () {
    actingAsBackendUser();

    $this->postJson('/api/backend/customers', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['company_name']);
});

it('can crud restore and force delete a customer without secrets', function () {
    actingAsBackendUser();

    $create = $this->postJson('/api/backend/customers', [
        'company_name' => 'Acme Backend',
        'max_users' => 12,
        'token' => 'super-secret-token',
        's3_secret' => 's3-secret',
        'google_api_key' => 'gkey',
    ])->assertCreated();

    $id = $create->json('data.id');
    expect($create->json('data.company_name'))->toBe('Acme Backend');
    assertCustomerHasNoSecrets($create->json('data'));
    expect($create->json('data.google_api_key_set'))->toBeTrue()
        ->and($create->json('data.s3_configured'))->toBeFalse()
        ->and($create->json('data.s3_partial'))->toBeTrue()
        ->and($create->json('data.customer_subscriptions_count'))->toBe(0)
        ->and($create->json('data.customer_users_count'))->toBe(0);

    $list = $this->getJson('/api/backend/customers?per_page=5')
        ->assertOk()
        ->assertJsonPath('data.0.company_name', 'Acme Backend');
    assertCustomerHasNoSecrets($list->json('data.0'));

    $this->putJson("/api/backend/customers/{$id}", [
        'company_name' => 'Acme Renamed',
    ])->assertOk()->assertJsonPath('data.company_name', 'Acme Renamed');

    $this->deleteJson("/api/backend/customers/{$id}")
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('id', $id);

    $this->assertSoftDeleted('customers', ['id' => $id]);

    $restored = $this->postJson("/api/backend/customers/{$id}/restore")
        ->assertOk()
        ->assertJsonPath('data.company_name', 'Acme Renamed');
    assertCustomerHasNoSecrets($restored->json('data'));
    expect($restored->json('data.google_api_key_set'))->toBeTrue();

    expect(Customer::query()->find($id))->not->toBeNull();

    $this->deleteJson("/api/backend/customers/{$id}")->assertOk();
    $this->deleteJson("/api/backend/customers/{$id}/force")
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->assertDatabaseMissing('customers', ['id' => $id]);
});

it('exposes counts and credential flags on list and show without leaking secrets', function () {
    actingAsBackendUser();

    $customer = createCustomerWithSubscriptions(2);
    $customer->update([
        'google_api_key' => 'gkey-live',
        's3_endpoint' => 'http://127.0.0.1:9005',
        's3_key' => 'access-key',
        's3_secret' => 'secret-key',
        's3_region' => 'us-east-1',
        's3_bucket' => 'bucket',
        's3_use_path_style_endpoint' => true,
        'token' => 'must-not-leak',
    ]);
    CustomerUser::factory()->count(3)->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'console_access' => false,
    ]);

    $list = $this->getJson('/api/backend/customers?per_page=25')->assertOk();
    $row = collect($list->json('data'))->firstWhere('id', $customer->id);
    assertCustomerHasNoSecrets($row);
    expect($row['google_api_key_set'])->toBeTrue()
        ->and($row['s3_configured'])->toBeTrue()
        ->and($row['s3_partial'])->toBeFalse()
        ->and($row['customer_subscriptions_count'])->toBe(2)
        ->and($row['customer_users_count'])->toBe(3);

    $show = $this->getJson("/api/backend/customers/{$customer->id}")->assertOk();
    assertCustomerHasNoSecrets($show->json('data'));
    expect($show->json('data.google_api_key_set'))->toBeTrue()
        ->and($show->json('data.s3_configured'))->toBeTrue()
        ->and($show->json('data.s3_partial'))->toBeFalse()
        ->and($show->json('data.customer_subscriptions_count'))->toBe(2)
        ->and($show->json('data.customer_users_count'))->toBe(3);
});

it('marks s3 as partial when only some required fields are filled', function () {
    actingAsBackendUser();

    $customer = Customer::factory()->create([
        'google_api_key' => null,
        's3_endpoint' => 'http://127.0.0.1:9005',
        's3_key' => 'access-key',
        's3_secret' => null,
        's3_bucket' => null,
    ]);

    $show = $this->getJson("/api/backend/customers/{$customer->id}")->assertOk();
    assertCustomerHasNoSecrets($show->json('data'));
    expect($show->json('data.google_api_key_set'))->toBeFalse()
        ->and($show->json('data.s3_configured'))->toBeFalse()
        ->and($show->json('data.s3_partial'))->toBeTrue();
});

it('rejects customer credentials without a token', function () {
    $customer = Customer::factory()->create();

    $this->getJson("/api/backend/customers/{$customer->id}/credentials")->assertUnauthorized();
});

it('forbids customer credentials without Shield View permission', function () {
    actingAsBackendForbidden();
    $customer = Customer::factory()->create();

    $this->getJson("/api/backend/customers/{$customer->id}/credentials")->assertForbidden();
});

it('returns 404 for credentials on a missing customer', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/customers/999999/credentials')->assertNotFound();
});

it('returns customer secrets on the credentials endpoint for authorized users', function () {
    actingAsBackendUser(['View:Customer']);

    $customer = Customer::factory()->create([
        'token' => 'reveal-token',
        'google_api_key' => 'reveal-gkey',
        's3_endpoint' => 'http://127.0.0.1:9005',
        's3_key' => 'reveal-key',
        's3_secret' => 'reveal-secret',
        's3_region' => 'eu-west-1',
        's3_bucket' => 'reveal-bucket',
        's3_use_path_style_endpoint' => true,
    ]);

    $this->getJson("/api/backend/customers/{$customer->id}/credentials")
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'token' => 'reveal-token',
                'google_api_key' => 'reveal-gkey',
                's3_endpoint' => 'http://127.0.0.1:9005',
                's3_key' => 'reveal-key',
                's3_secret' => 'reveal-secret',
                's3_region' => 'eu-west-1',
                's3_bucket' => 'reveal-bucket',
                's3_use_path_style_endpoint' => true,
                'mail_mailer' => null,
                'mail_transport' => null,
                'mail_host' => null,
                'mail_url' => null,
                'mail_port' => null,
                'mail_username' => null,
                'mail_password' => null,
                'mail_encryption' => null,
                'mail_scheme' => null,
                'mail_from_address' => null,
                'mail_from_name' => null,
                'mail_ehlo_domain' => null,
            ],
        ]);
});

it('reveals the mail settings on the credentials endpoint', function () {
    actingAsBackendUser(['View:Customer']);

    $customer = Customer::factory()->withMailSettings()->create();

    $this->getJson("/api/backend/customers/{$customer->id}/credentials")
        ->assertOk()
        ->assertJsonPath('data.mail_mailer', 'smtp')
        ->assertJsonPath('data.mail_host', 'mail.blackwidow.org.za')
        ->assertJsonPath('data.mail_port', 465)
        ->assertJsonPath('data.mail_password', 'Spider1962$#@!');
});

it('saves the per-customer mail settings and flags them as configured', function () {
    actingAsBackendUser();

    $customer = Customer::factory()->create();

    $response = $this->putJson("/api/backend/customers/{$customer->id}", [
        'mail_mailer' => 'smtp',
        'mail_host' => 'mail.blackwidow.org.za',
        'mail_port' => 465,
        'mail_username' => 'demo@blackwidow.org.za',
        'mail_password' => 'Spider1962$#@!',
        'mail_encryption' => 'null',
        'mail_from_address' => 'demo@blackwidow.org.za',
        'mail_ehlo_domain' => 'blackwidow.org.za',
    ])->assertOk();

    assertCustomerHasNoSecrets($response->json('data'));
    expect($response->json('data.mail_configured'))->toBeTrue()
        ->and($response->json('data.mail_password_set'))->toBeTrue()
        ->and($response->json('data.mail_mailer'))->toBe('smtp');

    $customer->refresh();
    expect($customer->mail_password)->toBe('Spider1962$#@!')
        ->and($customer->mail_port)->toBe(465);

    Queue::assertPushed(SyncCustomerEnvToSubscriptionsJob::class);
});

it('reports mail as not configured until mailer, host and from address are set', function () {
    actingAsBackendUser();

    $customer = Customer::factory()->create(['mail_mailer' => 'smtp', 'mail_host' => 'mail.blackwidow.org.za']);

    $this->getJson("/api/backend/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.mail_configured', false);
});

it('lets a mail setting be cleared back to the env template default', function () {
    actingAsBackendUser();

    $customer = Customer::factory()->withMailSettings()->create();

    $this->putJson("/api/backend/customers/{$customer->id}", ['mail_host' => null])
        ->assertOk()
        ->assertJsonPath('data.mail_configured', false);

    expect($customer->fresh()->mail_host)->toBeNull();
});

it('validates the mail settings', function (array $payload, string $field) {
    actingAsBackendUser();

    $customer = Customer::factory()->create();

    $this->putJson("/api/backend/customers/{$customer->id}", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$field]);
})->with([
    'unknown mailer' => [['mail_mailer' => 'pigeon'], 'mail_mailer'],
    'unknown transport' => [['mail_transport' => 'pigeon'], 'mail_transport'],
    'port out of range' => [['mail_port' => 70000], 'mail_port'],
    'port not a number' => [['mail_port' => 'four-six-five'], 'mail_port'],
    'from address not an email' => [['mail_from_address' => 'not-an-email'], 'mail_from_address'],
]);

it('queues an env sync for every subscription on demand', function () {
    actingAsBackendUser();

    $customer = Customer::factory()->withMailSettings()->create();

    $this->postJson("/api/backend/customers/{$customer->id}/sync-env")
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('customer_subscriptions_count', 0);

    Queue::assertPushed(
        SyncCustomerEnvToSubscriptionsJob::class,
        fn (SyncCustomerEnvToSubscriptionsJob $job) => $job->customerId === $customer->id
    );
});

it('refuses to sync env when the customer has nothing configured', function () {
    actingAsBackendUser();

    $customer = Customer::factory()->create(['google_api_key' => null]);

    $this->postJson("/api/backend/customers/{$customer->id}/sync-env")->assertStatus(422);

    Queue::assertNotPushed(SyncCustomerEnvToSubscriptionsJob::class);
});

it('forbids syncing env without Shield Update permission', function () {
    actingAsBackendForbidden();
    $customer = Customer::factory()->withMailSettings()->create();

    $this->postJson("/api/backend/customers/{$customer->id}/sync-env")->assertForbidden();
});
