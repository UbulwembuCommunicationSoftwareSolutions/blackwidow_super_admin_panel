<?php

use App\Helpers\ForgeApi;
use App\Jobs\SyncCustomerEnvToSubscriptionsJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use App\Services\CustomerEnvSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * @param  array<string, string|null>  $env
 */
function subscriptionWithEnv(Customer $customer, array $env, array $attributes = []): CustomerSubscription
{
    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $subscription = CustomerSubscription::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'subscription_type_id' => $type->id,
    ], $attributes));

    foreach ($env as $key => $value) {
        EnvVariables::query()->create([
            'customer_subscription_id' => $subscription->id,
            'key' => $key,
            'value' => $value,
        ]);
    }

    return $subscription;
}

function envValue(CustomerSubscription $subscription, string $key): ?string
{
    return EnvVariables::query()
        ->where('customer_subscription_id', $subscription->id)
        ->where('key', $key)
        ->value('value');
}

it('writes the customer google key and mail settings into the subscription env', function () {
    Queue::fake();

    $customer = Customer::factory()->withMailSettings()->create(['google_api_key' => 'gkey-live']);
    $subscription = subscriptionWithEnv($customer, [
        'GOOGLE_MAPS_API_KEY' => 'CHANGE_ME',
        'MAIL_MAILER' => 'log',
        'MAIL_HOST' => '127.0.0.1',
        'MAIL_PORT' => '2525',
        'MAIL_USERNAME' => 'null',
        'MAIL_PASSWORD' => 'CHANGE_ME',
        'MAIL_ENCRYPTION' => 'tls',
        'MAIL_FROM_ADDRESS' => 'hello@example.com',
        'MAIL_EHLO_DOMAIN' => 'example.com',
    ]);

    $changed = app(CustomerEnvSyncService::class)->syncSubscription($subscription);

    expect($changed)->toContain('GOOGLE_MAPS_API_KEY', 'MAIL_MAILER', 'MAIL_PASSWORD');
    expect(envValue($subscription, 'GOOGLE_MAPS_API_KEY'))->toBe('gkey-live');
    expect(envValue($subscription, 'MAIL_MAILER'))->toBe('smtp');
    expect(envValue($subscription, 'MAIL_HOST'))->toBe('mail.blackwidow.org.za');
    expect(envValue($subscription, 'MAIL_PORT'))->toBe('465');
    expect(envValue($subscription, 'MAIL_USERNAME'))->toBe('demo@blackwidow.org.za');
    expect(envValue($subscription, 'MAIL_PASSWORD'))->toBe('Spider1962$#@!');
    expect(envValue($subscription, 'MAIL_ENCRYPTION'))->toBe('null');
    expect(envValue($subscription, 'MAIL_FROM_ADDRESS'))->toBe('demo@blackwidow.org.za');
    expect(envValue($subscription, 'MAIL_EHLO_DOMAIN'))->toBe('blackwidow.org.za');
});

it('falls back to the mailer and host for MAIL_TRANSPORT and MAIL_URL', function () {
    Queue::fake();

    $customer = Customer::factory()->withMailSettings()->create();
    $subscription = subscriptionWithEnv($customer, [
        'MAIL_TRANSPORT' => 'log',
        'MAIL_URL' => 'stale.example.com',
    ]);

    app(CustomerEnvSyncService::class)->syncSubscription($subscription);

    expect(envValue($subscription, 'MAIL_TRANSPORT'))->toBe('smtp');
    expect(envValue($subscription, 'MAIL_URL'))->toBe('mail.blackwidow.org.za');
});

it('prefers an explicit transport and url over the fallbacks', function () {
    Queue::fake();

    $customer = Customer::factory()->withMailSettings()->create([
        'mail_transport' => 'sendmail',
        'mail_url' => 'smtp.override.test',
    ]);
    $subscription = subscriptionWithEnv($customer, [
        'MAIL_TRANSPORT' => 'log',
        'MAIL_URL' => 'stale.example.com',
    ]);

    app(CustomerEnvSyncService::class)->syncSubscription($subscription);

    expect(envValue($subscription, 'MAIL_TRANSPORT'))->toBe('sendmail');
    expect(envValue($subscription, 'MAIL_URL'))->toBe('smtp.override.test');
});

it('never adds a key the subscription env does not already have', function () {
    Queue::fake();

    $customer = Customer::factory()->withMailSettings()->create(['google_api_key' => 'gkey-live']);
    $subscription = subscriptionWithEnv($customer, ['APP_NAME' => 'Static Site']);

    app(CustomerEnvSyncService::class)->syncSubscription($subscription);

    $keys = EnvVariables::query()
        ->where('customer_subscription_id', $subscription->id)
        ->pluck('key')
        ->all();

    // SECURE_TOKEN is always upserted (tenant sync auth); other customer keys are not invented.
    expect($keys)->toEqualCanonicalizing(['APP_NAME', 'SECURE_TOKEN']);
    expect(envValue($subscription, 'SECURE_TOKEN'))->toBe($customer->token);
});

it('upserts SECURE_TOKEN from the customer token onto every subscription', function () {
    Queue::fake();

    $customer = Customer::factory()->create(['token' => 'customer-shared-secret']);
    $subscription = subscriptionWithEnv($customer, [
        'APP_NAME' => 'Firearm',
        'SECURE_TOKEN' => 'token',
    ]);

    $changed = app(CustomerEnvSyncService::class)->syncSubscription($subscription);

    expect($changed)->toContain('SECURE_TOKEN');
    expect(envValue($subscription, 'SECURE_TOKEN'))->toBe('customer-shared-secret');
});

it('creates SECURE_TOKEN when the subscription is missing it', function () {
    Queue::fake();

    $customer = Customer::factory()->create(['token' => 'customer-shared-secret']);
    $subscription = subscriptionWithEnv($customer, ['APP_NAME' => 'Firearm']);

    $changed = app(CustomerEnvSyncService::class)->syncSubscription($subscription);

    expect($changed)->toContain('SECURE_TOKEN');
    expect(envValue($subscription, 'SECURE_TOKEN'))->toBe('customer-shared-secret');
});

it('leaves the env template default in place for fields the customer has not filled', function () {
    Queue::fake();

    $customer = Customer::factory()->create([
        'google_api_key' => 'gkey-live',
        'mail_host' => null,
        'mail_from_name' => null,
    ]);
    $subscription = subscriptionWithEnv($customer, [
        'GOOGLE_MAPS_API_KEY' => 'CHANGE_ME',
        'MAIL_HOST' => 'mail.blackwidow.org.za',
        'MAIL_FROM_NAME' => '${APP_NAME}',
    ]);

    $changed = app(CustomerEnvSyncService::class)->syncSubscription($subscription);

    expect($changed)->toContain('GOOGLE_MAPS_API_KEY', 'SECURE_TOKEN');
    expect(envValue($subscription, 'MAIL_HOST'))->toBe('mail.blackwidow.org.za');
    expect(envValue($subscription, 'MAIL_FROM_NAME'))->toBe('${APP_NAME}');
    expect(envValue($subscription, 'SECURE_TOKEN'))->toBe($customer->token);
});

it('reports no changes for a customer with no google key or mail settings beyond SECURE_TOKEN', function () {
    Queue::fake();

    $customer = Customer::factory()->create([
        'token' => 'customer-shared-secret',
        'google_api_key' => null,
    ]);
    $subscription = subscriptionWithEnv($customer, [
        'MAIL_HOST' => 'mail.blackwidow.org.za',
        'SECURE_TOKEN' => 'customer-shared-secret',
    ]);

    expect(app(CustomerEnvSyncService::class)->syncSubscription($subscription))->toBe([]);
    expect(envValue($subscription, 'MAIL_HOST'))->toBe('mail.blackwidow.org.za');
});

it('queues an env sync when the customer token changes', function () {
    Queue::fake();

    $customer = Customer::factory()->create(['token' => 'old-token']);

    $customer->update(['token' => 'new-token']);

    Queue::assertPushed(
        SyncCustomerEnvToSubscriptionsJob::class,
        fn (SyncCustomerEnvToSubscriptionsJob $job) => $job->customerId === $customer->id
    );
});

it('applies the customer config through addMissingEnv so deployments pick it up', function () {
    Queue::fake();
    config(['services.forge.key' => 'test-forge-key']);

    $customer = Customer::factory()->withMailSettings()->create(['google_api_key' => 'gkey-live']);
    $type = SubscriptionType::factory()->create(['project_type' => 'static']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $type->id,
    ]);

    TemplateEnvVariables::query()->create([
        'subscription_type_id' => $type->id,
        'key' => 'GOOGLE_MAPS_API_KEY',
        'value' => 'CHANGE_ME',
    ]);
    TemplateEnvVariables::query()->create([
        'subscription_type_id' => $type->id,
        'key' => 'MAIL_HOST',
        'value' => 'CHANGE_ME',
    ]);

    (new ForgeApi)->addMissingEnv($subscription);

    expect(envValue($subscription, 'GOOGLE_MAPS_API_KEY'))->toBe('gkey-live');
    expect(envValue($subscription, 'MAIL_HOST'))->toBe('mail.blackwidow.org.za');
    expect(envValue($subscription, 'SECURE_TOKEN'))->toBe($customer->token);
});

it('queues an env sync when a mail setting changes', function () {
    Queue::fake();

    $customer = Customer::factory()->create();

    $customer->update(['mail_host' => 'mail.blackwidow.org.za']);

    Queue::assertPushed(
        SyncCustomerEnvToSubscriptionsJob::class,
        fn (SyncCustomerEnvToSubscriptionsJob $job) => $job->customerId === $customer->id
    );
});

it('queues an env sync when the google api key changes', function () {
    Queue::fake();

    $customer = Customer::factory()->create();

    $customer->update(['google_api_key' => 'gkey-live']);

    Queue::assertPushed(SyncCustomerEnvToSubscriptionsJob::class);
});

it('does not queue an env sync for unrelated customer edits', function () {
    Queue::fake();

    $customer = Customer::factory()->create();

    $customer->update(['company_name' => 'Renamed', 'max_users' => 99]);

    Queue::assertNotPushed(SyncCustomerEnvToSubscriptionsJob::class);
});

it('syncs every subscription and skips pushing the ones not on forge yet', function () {
    Queue::fake();

    $customer = Customer::factory()->withMailSettings()->create();
    $first = subscriptionWithEnv($customer, ['MAIL_HOST' => 'CHANGE_ME']);
    $second = subscriptionWithEnv($customer, ['MAIL_HOST' => 'CHANGE_ME'], [
        'server_id' => null,
        'forge_site_id' => null,
    ]);

    (new SyncCustomerEnvToSubscriptionsJob($customer->id))->handle(app(CustomerEnvSyncService::class));

    expect(envValue($first, 'MAIL_HOST'))->toBe('mail.blackwidow.org.za');
    expect(envValue($second, 'MAIL_HOST'))->toBe('mail.blackwidow.org.za');
    expect($first->fresh()->last_deployment_error)->toBeNull();
    expect($second->fresh()->last_deployment_error)->toBeNull();
});

it('does nothing when the customer has nothing configured to sync beyond an already-matching token', function () {
    Queue::fake();

    $customer = Customer::factory()->create([
        'token' => 'customer-shared-secret',
        'google_api_key' => null,
    ]);
    $subscription = subscriptionWithEnv($customer, [
        'MAIL_HOST' => 'CHANGE_ME',
        'SECURE_TOKEN' => 'customer-shared-secret',
    ]);

    (new SyncCustomerEnvToSubscriptionsJob($customer->id))->handle(app(CustomerEnvSyncService::class));

    expect(envValue($subscription, 'MAIL_HOST'))->toBe('CHANGE_ME');
    expect(envValue($subscription, 'SECURE_TOKEN'))->toBe('customer-shared-secret');
});
