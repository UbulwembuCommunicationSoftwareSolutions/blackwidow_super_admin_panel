<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\EnvVariables;
use App\Models\ForgeServer;
use App\Models\Reporting\Aims\CustomerDashboard;
use App\Models\Reporting\Aims\Dashboard;
use App\Services\Reporting\Aims\AimsFilters;
use App\Services\Reporting\Aims\AimsReportingSettingsStore;
use App\Services\Reporting\Aims\AimsReportService;
use App\Services\Reporting\Aims\AimsSchemaSql;
use App\Services\Reporting\Aims\AimsScope;
use App\Services\Reporting\Aims\TenantConnectionFactory;
use App\Services\Reporting\Aims\TenantDatabaseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Http::fake();
    config(['user_sync.enabled' => false]);
});

it('rejects dashboards without a token', function () {
    $this->getJson('/api/backend/dashboards')->assertUnauthorized();
});

it('forbids dashboards without the shield permission', function () {
    actingAsBackendForbidden();

    $this->getJson('/api/backend/dashboards')->assertForbidden();
});

it('seeds the aims case dashboard and lets staff manage the catalog', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/dashboards?search=aims-cases')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'aims-cases')
        ->assertJsonPath('data.0.handler', Dashboard::HANDLER_AIMS_CASES);

    $created = $this->postJson('/api/backend/dashboards', [
        'name' => 'AIMS copy',
        'slug' => 'aims-copy',
        'description' => 'Second handler slot',
        'subscription_type_id' => 1,
        'handler' => 'aims_cases',
    ])->assertCreated();

    $id = $created->json('data.id');
    $this->putJson("/api/backend/dashboards/{$id}", ['name' => 'AIMS copy renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'AIMS copy renamed');
    $this->deleteJson("/api/backend/dashboards/{$id}")->assertOk();
    expect(Dashboard::query()->find($id))->toBeNull();
});

it('grants a dashboard to a customer subscription and to that customers users', function () {
    actingAsBackendUser();
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'database_name' => 'ciid_aims',
        'database_user' => 'ciid_aims',
        'database_password' => 'secret',
    ]);
    $dashboard = Dashboard::query()->where('slug', 'aims-cases')->firstOrFail();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'is_system_admin' => false,
    ]);
    $outsider = CustomerUser::factory()->create([
        'customer_id' => Customer::factory()->create()->id,
        'skip_sync' => true,
    ]);

    $created = $this->postJson('/api/backend/customer-dashboards', [
        'customer_id' => $customer->id,
        'dashboard_id' => $dashboard->id,
        'customer_subscription_id' => $subscription->id,
    ])->assertCreated()
        ->assertJsonPath('data.reporting_database', 'ciid_aims_rpt')
        ->assertJsonPath('data.enabled', true)
        ->assertJsonMissingPath('data.customer_subscription.database_password');

    $id = $created->json('data.id');

    $this->putJson("/api/backend/customer-dashboards/{$id}/users", [
        'customer_user_ids' => [$user->id],
    ])->assertOk()
        ->assertJsonPath('data.customer_users.0.id', $user->id)
        ->assertJsonMissingPath('data.customer_users.0.password');

    $this->putJson("/api/backend/customer-dashboards/{$id}/users", [
        'customer_user_ids' => [$outsider->id],
    ])->assertUnprocessable();

    $this->postJson('/api/backend/customer-dashboards', [
        'customer_id' => $customer->id,
        'dashboard_id' => $dashboard->id,
        'customer_subscription_id' => $subscription->id,
    ])->assertUnprocessable();
});

it('lets a customer admin manage user grants on their own dashboard only', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    actingAsCustomerAdmin($customer);
    $own = CustomerDashboard::factory()->create([
        'customer_id' => $customer->id,
        'customer_subscription_id' => CustomerSubscription::factory()->create([
            'customer_id' => $customer->id,
            'database_name' => 'own_aims',
        ])->id,
        'reporting_database' => 'own_aims_rpt',
    ]);
    $foreign = CustomerDashboard::factory()->create([
        'customer_id' => $other->id,
        'reporting_database' => 'other_aims_rpt',
    ]);
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'is_system_admin' => false,
    ]);

    $this->getJson('/api/backend/customer-dashboards?customer_id='.$customer->id)
        ->assertOk()
        ->assertJsonPath('data.0.id', $own->id);

    $this->getJson('/api/backend/customer-dashboards/'.$foreign->id)->assertNotFound();

    $this->putJson('/api/backend/customer-dashboards/'.$own->id.'/users', [
        'customer_user_ids' => [$user->id],
    ])->assertOk();

    $this->putJson('/api/backend/customer-dashboards/'.$own->id, ['enabled' => false])->assertForbidden();
    $this->postJson('/api/backend/dashboards', [
        'name' => 'Nope',
        'slug' => 'nope',
        'handler' => 'aims_cases',
    ])->assertForbidden();
});

it('returns a safe error when the customer database cannot be reached', function () {
    actingAsBackendUser();
    $grant = CustomerDashboard::factory()->create([
        'reporting_database' => 'missing_rpt',
        'enabled' => true,
    ]);

    $this->mock(TenantConnectionFactory::class, function ($mock) {
        $mock->shouldReceive('connect')->once()->andThrow(new TenantDatabaseException('The customer database could not be reached.'));
        $mock->shouldReceive('disconnect');
    });

    $this->getJson('/api/backend/customer-dashboards/'.$grant->id.'/report/me')
        ->assertStatus(503)
        ->assertJsonPath('error', 'The customer database could not be reached.');
});

it('does not open a dashboard that is turned off', function () {
    actingAsBackendUser();
    $grant = CustomerDashboard::factory()->create(['enabled' => false]);

    $this->getJson('/api/backend/customer-dashboards/'.$grant->id.'/report/dashboard')
        ->assertForbidden()
        ->assertJsonPath('error', 'This dashboard is turned off.');
});

it('resolves the mysql host from the forge server when the api is elsewhere', function () {
    $server = ForgeServer::query()->create([
        'forge_server_id' => 4242,
        'name' => 'Cape Town',
        'ip_address' => '203.0.113.10',
    ]);
    $subscription = CustomerSubscription::factory()->create([
        'server_id' => $server->forge_server_id,
        'database_name' => 'site_db',
    ]);
    EnvVariables::query()->create([
        'customer_subscription_id' => $subscription->id,
        'key' => 'DB_PORT',
        'value' => '3307',
    ]);

    $factory = app(TenantConnectionFactory::class);

    config(['reporting.api_server_ip' => '203.0.113.10']);
    expect($factory->resolveHost($subscription, null))->toBe('127.0.0.1');

    config(['reporting.api_server_ip' => '198.51.100.8']);
    expect($factory->resolveHost($subscription, null))->toBe('203.0.113.10');

    config(['reporting.api_server_ip' => null]);
    expect($factory->resolveHost($subscription, null))->toBe('127.0.0.1');
    expect($factory->resolveHost($subscription, '10.0.0.4'))->toBe('10.0.0.4');
    expect($factory->resolvePort($subscription))->toBe(3307);
});

it('shows aims report settings as key value pairs', function () {
    actingAsBackendUser();
    $grant = CustomerDashboard::factory()->create();

    $this->mock(AimsReportingSettingsStore::class, function ($mock) {
        $mock->shouldReceive('read')->once()->andReturn([
            'settings' => [
                ['key' => 'investigator_role_ids', 'value' => '17, 23'],
            ],
            'roles' => [
                ['id' => 17, 'name' => 'Investigations Officer'],
            ],
        ]);
    });

    $this->getJson('/api/backend/customer-dashboards/'.$grant->id.'/settings')
        ->assertOk()
        ->assertJsonPath('data.settings.0.value', '17, 23')
        ->assertJsonPath('data.roles.0.name', 'Investigations Officer');
});

it('rejects an unknown aims setting before opening the customer database', function () {
    actingAsBackendUser();
    $grant = CustomerDashboard::factory()->create();

    $this->putJson('/api/backend/customer-dashboards/'.$grant->id.'/settings', [
        'settings' => [
            ['key' => 'not_a_setting', 'value' => '1'],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['settings.0.key']);
});

it('does not open settings for a dashboard that is not aims', function () {
    actingAsBackendUser();
    $dashboard = Dashboard::factory()->create(['handler' => 'other']);
    $grant = CustomerDashboard::factory()->create(['dashboard_id' => $dashboard->id]);

    $this->getJson('/api/backend/customer-dashboards/'.$grant->id.'/settings')
        ->assertStatus(422)
        ->assertJsonPath('error', 'This dashboard has no settings yet.');
});

it('keeps report settings away from a customer admin', function () {
    $admin = actingAsCustomerAdmin();
    $grant = CustomerDashboard::factory()->create(['customer_id' => $admin->customer_id]);

    $this->getJson('/api/backend/customer-dashboards/'.$grant->id.'/settings')
        ->assertForbidden()
        ->assertJsonPath('message', 'Customer admins cannot change report settings.');
});

it('keeps a report grant when the role display name no longer matches the seed name', function () {
    $grants = [
        [
            'role_ids' => json_encode([17, 23]),
            'scope' => 'participant',
            'can_dashboard' => 0,
            'can_cases' => 1,
            'can_audit' => 0,
            'can_export' => 0,
            'can_see_names' => 1,
            'note' => 'seeded as Investigator, since renamed',
        ],
        [
            'role_ids' => '[4]',
            'scope' => 'all',
            'can_dashboard' => 1,
            'can_cases' => 1,
            'can_audit' => 1,
            'can_export' => 1,
            'can_see_names' => 1,
        ],
        [
            'role_ids' => [9],
            'scope' => 'none',
            'can_dashboard' => 0,
            'can_cases' => 0,
            'can_audit' => 1,
            'can_export' => 0,
            'can_see_names' => 0,
        ],
    ];

    $renamed = AimsReportService::capabilitiesFor([17], $grants);

    expect($renamed['scopes'])->toBe(['participant'])
        ->and($renamed['can']['cases'])->toBeTrue()
        ->and($renamed['can']['dashboard'])->toBeFalse()
        ->and($renamed['can']['export'])->toBeFalse()
        ->and($renamed['can']['names'])->toBeTrue()
        ->and($renamed['can']['audit'])->toBeFalse();

    expect(AimsReportService::capabilitiesFor([23], $grants)['scopes'])->toBe(['participant']);

    $widest = AimsReportService::capabilitiesFor([17, 4], $grants);

    expect($widest['scopes'])->toBe(['all'])
        ->and($widest['can']['dashboard'])->toBeTrue()
        ->and($widest['can']['audit'])->toBeTrue();

    $auditOnly = AimsReportService::capabilitiesFor([9], $grants);

    expect($auditOnly['scopes'])->toBe([])
        ->and($auditOnly['can']['audit'])->toBeTrue()
        ->and($auditOnly['can']['cases'])->toBeFalse();

    $unknown = AimsReportService::capabilitiesFor([99], $grants);

    expect($unknown['scopes'])->toBe([])
        ->and($unknown['can']['cases'])->toBeFalse()
        ->and($unknown['can']['audit'])->toBeFalse();
});

it('keeps aims role scope and drops filters that are not on the whitelist', function () {
    [$sql, $params] = AimsScope::clause(['id' => 9, 'scopes' => ['participant']], 'aims_db');

    expect($sql)->toContain('`aims_db`.`docket_participants`')
        ->and($sql)->not->toContain('1=1')
        ->and($params)->toBe(['9', '9']);

    expect(AimsScope::clause(['id' => 1, 'scopes' => []], 'aims_db')[0])->toBe('1=0');
    expect(AimsScope::label(['scopes' => ['all']]))->toBe('All cases');

    $clean = AimsFilters::clean([
        'classification' => 'INV',
        'state' => 'open',
        'q' => 'ABC',
        'dropped' => 'ignore-me',
        'from' => 'not-a-date',
    ]);

    expect($clean)->toBe([
        'classification' => 'INV',
        'state' => 'open',
        'q' => 'ABC',
    ]);
});

it('rewrites the reporting schema for one customer database', function () {
    $schema = app(AimsSchemaSql::class);
    $sql = $schema->render($schema->template('02_reporting_schema_ethekwini.sql'), 'tenant_aims', 'tenant_aims_rpt');

    expect($sql)->not->toContain('ciid_console_aims_net_za')
        ->and($sql)->toContain('`tenant_aims`.dockets')
        ->and($sql)->toContain('USE `tenant_aims_rpt`')
        ->and($sql)->not->toContain('`aims_rpt`');
});

it('installs with the super admin database user', function () {
    config([
        'reporting.install_username' => 'forge',
        'reporting.install_password' => 'forge-secret',
    ]);

    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'database_name' => 'tenant_aims',
        'database_user' => 'tenant_aims',
    ]);
    $grant = CustomerDashboard::factory()->create([
        'customer_id' => $customer->id,
        'customer_subscription_id' => $subscription->id,
        'reporting_database' => 'tenant_aims_rpt',
    ]);

    $connection = app(TenantConnectionFactory::class)->installationConnection($grant);

    expect($connection['username'])->toBe('forge')
        ->and($connection['password'])->toBe('forge-secret')
        ->and($connection['database'])->toBe('tenant_aims');

    $this->mock(TenantConnectionFactory::class, function ($mock) {
        $mock->shouldReceive('connectForInstall')->once()->andThrow(new TenantDatabaseException('The install database user could not connect.'));
        $mock->shouldNotReceive('connect');
    });

    $this->artisan('reporting:install-aims', [
        'customer_dashboard' => $grant->id,
    ])->assertFailed();
});

it('reads a select result before running the next install statement', function () {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $schema = app(AimsSchemaSql::class);
    $schema->execute($pdo, 'CREATE TABLE sample (id INTEGER)');
    $schema->execute($pdo, 'INSERT INTO sample (id) VALUES (1)');
    $schema->execute($pdo, 'SELECT COUNT(*) AS aims_database_is_readable FROM sample WHERE 1 = 0');
    $schema->execute($pdo, 'SELECT id FROM sample');

    expect($pdo->query('SELECT id FROM sample')->fetchColumn())->toBe(1);
});

it('writes substituted sql without connecting when asked', function () {
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'database_name' => 'tenant_aims',
        'database_user' => 'tenant_aims',
    ]);
    $grant = CustomerDashboard::factory()->create([
        'customer_id' => $customer->id,
        'customer_subscription_id' => $subscription->id,
        'reporting_database' => 'tenant_aims_rpt',
    ]);

    $this->artisan('reporting:install-aims', [
        'customer_dashboard' => $grant->id,
        '--sql-only' => true,
    ])->assertSuccessful();

    $path = 'reporting/aims-'.$grant->id.'.sql';
    $sql = Storage::disk('local')->get($path);

    expect($sql)->toContain('CREATE DATABASE IF NOT EXISTS `tenant_aims_rpt`')
        ->and($sql)->not->toContain('ciid_console_aims_net_za');

    Storage::disk('local')->delete($path);
});
