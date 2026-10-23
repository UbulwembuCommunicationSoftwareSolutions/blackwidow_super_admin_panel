<?php

use App\Helpers\ForgeApi;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Support\SiteEnv\SiteEnvReader;
use Illuminate\Encryption\Encrypter;
use Laravel\Forge\Forge;

beforeEach(function () {
    config([
        'services.forge.key' => 'test-key',
        'services.forge.organization' => 'acme',
    ]);
});

it('keeps the live app key when the stored key is the template placeholder', function () {
    $liveKey = usableAppKey();
    $subscription = subscriptionReadyForEnvPush();
    envRow($subscription, 'APP_KEY', 'base64:CHANGE_ME');
    envRow($subscription, 'MAIL_HOST', 'smtp.example.test');

    $pushed = captureEnvPush($subscription, "APP_KEY={$liveKey}\nMAIL_HOST=old.example\n");

    $parsed = SiteEnvReader::parse($pushed);
    expect($parsed['APP_KEY'])->toBe($liveKey)
        ->and($parsed['MAIL_HOST'])->toBe('smtp.example.test')
        ->and(envRowValue($subscription, 'APP_KEY'))->toBe($liveKey);
});

it('writes a stored usable app key when the site does not have one yet', function () {
    $storedKey = usableAppKey();
    $subscription = subscriptionReadyForEnvPush();
    envRow($subscription, 'APP_KEY', $storedKey);
    envRow($subscription, 'MAIL_HOST', 'smtp.example.test');

    $pushed = captureEnvPush($subscription, "APP_ENV=production\n");

    $parsed = SiteEnvReader::parse($pushed);
    expect($parsed['APP_KEY'])->toBe($storedKey)
        ->and($parsed['MAIL_HOST'])->toBe('smtp.example.test')
        ->and(envRowValue($subscription, 'APP_KEY'))->toBe($storedKey);
});

it('omits the app key when neither the site nor the stored value is usable', function () {
    $subscription = subscriptionReadyForEnvPush();
    envRow($subscription, 'APP_KEY', 'base64:CHANGE_ME');
    envRow($subscription, 'MAIL_HOST', 'smtp.example.test');

    $pushed = captureEnvPush($subscription, "APP_ENV=production\n");

    $parsed = SiteEnvReader::parse($pushed);
    expect($parsed)->not->toHaveKey('APP_KEY')
        ->and($parsed['MAIL_HOST'])->toBe('smtp.example.test')
        ->and($pushed)->not->toContain('APP_KEY')
        ->and(envRowValue($subscription, 'APP_KEY'))->toBe('base64:CHANGE_ME');
});

function usableAppKey(): string
{
    return 'base64:'.base64_encode(Encrypter::generateKey('aes-256-cbc'));
}

function subscriptionReadyForEnvPush(): CustomerSubscription
{
    $type = SubscriptionType::factory()->create(['project_type' => 'static']);

    return CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'server_id' => 42,
        'forge_site_id' => '15',
    ]);
}

function envRow(CustomerSubscription $subscription, string $key, string $value): void
{
    EnvVariables::query()->create([
        'customer_subscription_id' => $subscription->id,
        'key' => $key,
        'value' => $value,
    ]);
}

function envRowValue(CustomerSubscription $subscription, string $key): ?string
{
    return EnvVariables::query()
        ->where('customer_subscription_id', $subscription->id)
        ->where('key', $key)
        ->value('value');
}

function captureEnvPush(CustomerSubscription $subscription, string $liveEnv): string
{
    $pushed = null;
    $forge = Mockery::mock(Forge::class);
    $forge->shouldReceive('siteEnvironment')
        ->once()
        ->withArgs(function ($organization, $serverId, $siteId) {
            return $organization === 'acme' && (int) $serverId === 42 && (int) $siteId === 15;
        })
        ->andReturn($liveEnv);
    $forge->shouldReceive('updateSiteEnvironment')
        ->once()
        ->withArgs(function ($organization, $serverId, $siteId, $content) use (&$pushed) {
            $pushed = $content;

            return $organization === 'acme' && (int) $serverId === 42 && (int) $siteId === 15;
        });

    $api = new ForgeApi;
    $api->forge = $forge;
    $api->sendEnv($subscription);

    expect($pushed)->toBeString();

    return $pushed;
}
