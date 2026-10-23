<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Support\SiteEnv\SiteEnvPuller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Creating a customer pushes it to the live LMS tenant: never let a test reach the network.
beforeEach(fn () => Http::fake());

function pullSubscription(?string $syncedAt): CustomerSubscription
{
    $type = SubscriptionType::factory()->create(['project_type' => 'static']);

    return CustomerSubscription::factory()->create([
        'customer_id' => Customer::factory()->create()->id,
        'subscription_type_id' => $type->id,
        'env_synced_at' => $syncedAt,
    ]);
}

function pullRow(CustomerSubscription $subscription, string $key, string $value, string $updatedAt): EnvVariables
{
    $row = new EnvVariables(['customer_subscription_id' => $subscription->id, 'key' => $key, 'value' => $value]);
    $row->timestamps = false;
    $row->created_at = $updatedAt;
    $row->updated_at = $updatedAt;
    $row->save();

    return $row;
}

function pullValue(CustomerSubscription $subscription, string $key): ?string
{
    return EnvVariables::query()->where('customer_subscription_id', $subscription->id)->where('key', $key)->value('value');
}

it('takes the site value for a row nobody edited since the last sync', function () {
    $subscription = pullSubscription('2026-10-23 10:00:00');
    pullRow($subscription, 'DB_PASSWORD', 'stale', '2026-10-01 08:00:00');

    $result = (new SiteEnvPuller)->pull($subscription, "DB_PASSWORD=real\n", true, true);

    expect($result['change'])->toBe(['DB_PASSWORD'])
        ->and(pullValue($subscription, 'DB_PASSWORD'))->toBe('real');
});

it('leaves a row alone that was edited in Super Admin after the last sync', function () {
    $subscription = pullSubscription('2026-10-23 10:00:00');
    pullRow($subscription, 'QUEUE_CONNECTION', 'redis', '2026-10-23 11:00:00');

    $result = (new SiteEnvPuller)->pull($subscription, "QUEUE_CONNECTION=database\n", true, true);

    expect($result['pending'])->toBe(['QUEUE_CONNECTION'])
        ->and($result['change'])->toBe([])
        ->and(pullValue($subscription, 'QUEUE_CONNECTION'))->toBe('redis');
});

it('does not advance the sync time while an unpushed edit is waiting', function () {
    $subscription = pullSubscription('2026-10-23 10:00:00');
    pullRow($subscription, 'QUEUE_CONNECTION', 'redis', '2026-10-23 11:00:00');

    (new SiteEnvPuller)->pull($subscription, "QUEUE_CONNECTION=database\n", true, true);

    expect($subscription->fresh()->env_synced_at->format('Y-m-d H:i:s'))->toBe('2026-10-23 10:00:00');
});

it('advances the sync time once every row matches the site', function () {
    $subscription = pullSubscription('2026-10-20 10:00:00');
    pullRow($subscription, 'APP_NAME', 'Old', '2026-10-01 08:00:00');

    (new SiteEnvPuller)->pull($subscription, "APP_NAME=New\n", true, true);

    expect($subscription->fresh()->env_synced_at->gt('2026-10-20 10:00:00'))->toBeTrue();
});

it('does not turn a pulled value into an unpushed edit', function () {
    $subscription = pullSubscription('2026-10-23 10:00:00');
    $row = pullRow($subscription, 'APP_NAME', 'Old', '2026-10-01 08:00:00');

    (new SiteEnvPuller)->pull($subscription, "APP_NAME=New\n", true, true);

    expect($row->fresh()->updated_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 08:00:00');
});

it('only adds new keys for a subscription that was never in sync', function () {
    $subscription = pullSubscription(null);
    pullRow($subscription, 'APP_NAME', 'Panel', '2026-10-01 08:00:00');

    $result = (new SiteEnvPuller)->pull($subscription, "APP_NAME=ForgeDefault\nSESSION_DRIVER=file\n", true, true);

    expect($result['add'])->toBe(['SESSION_DRIVER'])
        ->and($result['pending'])->toBe(['APP_NAME'])
        ->and(pullValue($subscription, 'APP_NAME'))->toBe('Panel')
        ->and(pullValue($subscription, 'SESSION_DRIVER'))->toBe('file');
});

it('keeps rows the site does not have and an empty site value never blanks a row', function () {
    $subscription = pullSubscription('2026-10-23 10:00:00');
    pullRow($subscription, 'ONLY_HERE', 'x', '2026-10-01 08:00:00');
    pullRow($subscription, 'MAIL_HOST', 'smtp.example.test', '2026-10-01 08:00:00');

    $result = (new SiteEnvPuller)->pull($subscription, "MAIL_HOST=\n", true, true);

    expect($result['only_here'])->toBe(['ONLY_HERE'])
        ->and($result['kept'])->toBe(['MAIL_HOST'])
        ->and(pullValue($subscription, 'MAIL_HOST'))->toBe('smtp.example.test');
});

it('writes nothing on a dry run and never stores the Forge API key', function () {
    $subscription = pullSubscription('2026-10-23 10:00:00');
    pullRow($subscription, 'DB_PASSWORD', 'stale', '2026-10-01 08:00:00');

    $result = (new SiteEnvPuller)->pull($subscription, "DB_PASSWORD=real\nFORGE_API_KEY=secret\n", false, true);

    expect($result['change'])->toBe(['DB_PASSWORD'])
        ->and($result['add'])->toBe([])
        ->and(pullValue($subscription, 'DB_PASSWORD'))->toBe('stale')
        ->and(pullValue($subscription, 'FORGE_API_KEY'))->toBeNull();
});
