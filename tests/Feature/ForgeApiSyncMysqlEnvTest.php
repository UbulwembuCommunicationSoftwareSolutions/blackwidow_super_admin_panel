<?php

use App\Helpers\ForgeApi;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('syncMysqlEnvFromSubscription creates DB_* env rows with truncated DB_USERNAME', function () {
    config(['services.forge.key' => 'test-forge-key']);

    $type = SubscriptionType::factory()->create(['project_type' => 'php']);
    $customer = Customer::factory()->create();
    $longDb = 'ekasilamfirearms_firearm_blackwidow';
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'database_name' => $longDb,
        'database_password' => 'secret-password-at-least-for-subscription',
    ]);

    $subscription->refresh();
    expect($subscription->forgeMysqlUser())->toHaveLength(\App\Models\CustomerSubscription::MYSQL_USER_NAME_MAX_LENGTH);

    (new ForgeApi)->syncMysqlEnvFromSubscription($subscription);

    $subscription->refresh();
    $expectedUser = $subscription->forgeMysqlUser();

    $userRow = EnvVariables::query()
        ->where('customer_subscription_id', $subscription->id)
        ->where('key', 'DB_USERNAME')
        ->first();
    expect($userRow)->not->toBeNull();
    expect($userRow->value)->toBe($expectedUser);

    $dbRow = EnvVariables::query()
        ->where('customer_subscription_id', $subscription->id)
        ->where('key', 'DB_DATABASE')
        ->first();
    expect($dbRow)->not->toBeNull();
    expect($dbRow->value)->toBe($subscription->forgeMysqlIdentifier());
});

it('syncMysqlEnvFromSubscription overwrites stale long DB_USERNAME', function () {
    config(['services.forge.key' => 'test-forge-key']);

    $type = SubscriptionType::factory()->create(['project_type' => 'php']);
    $customer = Customer::factory()->create();
    $longDb = 'ekasilamfirearms_firearm_blackwidow';
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => $customer->id,
        'database_name' => $longDb,
        'database_password' => 'secret-password-at-least-for-subscription',
    ]);

    EnvVariables::query()->create([
        'customer_subscription_id' => $subscription->id,
        'key' => 'DB_USERNAME',
        'value' => $longDb,
    ]);

    (new ForgeApi)->syncMysqlEnvFromSubscription($subscription->fresh());

    $subscription->refresh();
    $row = EnvVariables::query()
        ->where('customer_subscription_id', $subscription->id)
        ->where('key', 'DB_USERNAME')
        ->first();
    expect($row->value)->toBe($subscription->forgeMysqlUser());
    expect($row->value)->not->toBe($longDb);
});
