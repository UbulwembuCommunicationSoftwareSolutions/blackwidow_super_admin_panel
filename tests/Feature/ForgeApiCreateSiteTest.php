<?php

use App\Helpers\ForgeApi;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use Laravel\Forge\CursorPaginator;
use Laravel\Forge\Exceptions\ValidationException;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Site;

beforeEach(function () {
    config([
        'services.forge.key' => 'test-key',
        'services.forge.organization' => 'acme',
    ]);
});

it('creates every site with zero downtime deployments and records that on the subscription', function () {
    $type = SubscriptionType::factory()->create([
        'public_dir' => '/public',
        'github_repo' => 'acme/app',
        'branch' => 'main',
        'project_type' => 'static',
    ]);
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'domain' => 'demo.example.test',
        'server_id' => 42,
    ]);

    $forge = Mockery::mock(Forge::class);
    $forge->shouldReceive('createSite')
        ->once()
        ->withArgs(function (string $organization, int $serverId, array $payload) {
            return $organization === 'acme'
                && $serverId === 42
                && $payload['name'] === 'demo.example.test'
                && $payload['zero_downtime_deployments'] === true
                && $payload['shared_paths'] === [['from' => 'storage', 'to' => 'storage']]
                && $payload['repository'] === 'acme/app'
                && $payload['push_to_deploy'] === false;
        })
        ->andReturn(new Site([
            'id' => '15',
            'attributes' => [
                'zero_downtime_deployments' => true,
            ],
        ]));

    $api = new ForgeApi;
    $api->forge = $forge;
    $api->createSite(42, $subscription);

    $subscription->refresh();

    expect($subscription->forge_site_id)->toBe('15')
        ->and($subscription->zero_downtime_deployments)->toBeTrue();
});

it('keeps an adopted site on in-place deploys when Forge did not enable zero downtime', function () {
    $type = SubscriptionType::factory()->create([
        'public_dir' => '/dist',
        'github_repo' => null,
        'project_type' => 'static',
    ]);
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'domain' => 'legacy.example.test',
        'server_id' => 7,
    ]);

    $existing = new Site([
        'id' => '77',
        'attributes' => [
            'name' => 'legacy.example.test',
            'zero_downtime_deployments' => false,
        ],
    ]);
    $paginator = Mockery::mock(CursorPaginator::class);
    $paginator->shouldReceive('lazy')->andReturn((function () use ($existing) {
        yield $existing;
    })());

    $forge = Mockery::mock(Forge::class);
    $forge->shouldReceive('createSite')
        ->once()
        ->andThrow(new ValidationException(['name' => ['The name has already been taken.']]));
    $forge->shouldReceive('serverSites')->once()->with('acme', 7)->andReturn($paginator);

    $api = new ForgeApi;
    $api->forge = $forge;
    $api->createSite(7, $subscription);

    $subscription->refresh();

    expect($subscription->forge_site_id)->toBe('77')
        ->and($subscription->zero_downtime_deployments)->toBeFalse();
});

it('runs follow-up commands inside the current release for zero downtime sites', function () {
    $type = SubscriptionType::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'domain' => 'demo.example.test',
        'server_id' => 42,
        'forge_site_id' => '15',
    ]);
    $subscription->forceFill(['zero_downtime_deployments' => true])->save();

    $forge = Mockery::mock(Forge::class);
    $forge->shouldReceive('createCommand')
        ->once()
        ->withArgs(function ($organization, $serverId, $siteId, $data) {
            return $organization === 'acme'
                && (int) $serverId === 42
                && (string) $siteId === '15'
                && $data === ['command' => 'cd current && php artisan migrate --force'];
        });
    $forge->shouldReceive('createBackgroundProcess')
        ->once()
        ->withArgs(function ($organization, $serverId, $data) {
            return $organization === 'acme'
                && (int) $serverId === 42
                && $data === ['command' => 'php /home/forge/demo.example.test/current/artisan horizon'];
        });

    $api = new ForgeApi;
    $api->forge = $forge;
    $api->sendCommand($subscription->id, 'php artisan migrate --force');
    $api->horizonCreator($subscription->fresh());
});

it('records the zero downtime flag Forge reports when syncing sites', function () {
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory(),
        'customer_id' => Customer::factory(),
        'domain' => 'demo.example.test',
        'server_id' => 42,
    ]);

    $site = new Site([
        'id' => '15',
        'attributes' => [
            'name' => 'demo.example.test',
            'zero_downtime_deployments' => true,
        ],
    ]);
    $paginator = Mockery::mock(CursorPaginator::class);
    $paginator->shouldReceive('lazy')->andReturn((function () use ($site) {
        yield $site;
    })());

    $forge = Mockery::mock(Forge::class);
    $forge->shouldReceive('serverSites')->once()->with('acme', 42)->andReturn($paginator);

    $api = new ForgeApi;
    $api->forge = $forge;
    $api->getSitesForServer(42);

    $subscription->refresh();

    expect($subscription->forge_site_id)->toBe('15')
        ->and($subscription->zero_downtime_deployments)->toBeTrue();
});
