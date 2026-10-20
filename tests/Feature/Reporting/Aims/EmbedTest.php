<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\Reporting\Aims\CustomerDashboard;
use App\Models\Reporting\Aims\Dashboard;
use App\Services\Reporting\Aims\TenantConnectionFactory;
use App\Services\Reporting\Aims\TenantDatabaseException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'user_sync.enabled' => false,
        'reporting.frontend_url' => 'https://reports.test',
    ]);
});

it('pushes an enabled grant to the cms and hides it when the grant is removed', function () {
    config(['user_sync.enabled' => true]);
    Http::fake();

    $customer = Customer::factory()->create(['token' => 'cms-token']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.example.test',
    ]);
    $dashboard = Dashboard::factory()->create([
        'name' => 'AIMS Cases',
        'description' => 'Case report',
    ]);
    $grant = CustomerDashboard::factory()->create([
        'customer_id' => $customer->id,
        'dashboard_id' => $dashboard->id,
        'customer_subscription_id' => $subscription->id,
        'enabled' => true,
    ]);

    Http::assertSent(function ($request) use ($grant) {
        return $request->url() === 'https://cms.example.test/admin-api/v1/sync/external-dashboards'
            && $request['sync_key'] === 'aims-customer-dashboard:'.$grant->id
            && $request['heading'] === 'AIMS Cases'
            && $request['description'] === 'Case report'
            && $request['enabled'] === true
            && $request->hasHeader('Authorization', 'Bearer cms-token');
    });

    $grant->delete();

    Http::assertSent(fn ($request) => ($request['sync_key'] ?? null) === 'aims-customer-dashboard:'.$grant->id
        && ($request['enabled'] ?? null) === false);
});

it('mints a shell-less url for a customer user on the pivot', function () {
    [$customer, $subscription, $grant, $user] = embedFixture();
    $grant->customerUsers()->attach($user->id);

    $response = $this->withToken($customer->token)->postJson('/api/v1/embed/customer-dashboards/'.$grant->id, [
        'app_url' => 'https://cms.example.test',
        'email' => strtoupper($user->email_address),
    ]);

    $response->assertOk();
    expect($response->json('url'))->toStartWith('https://reports.test/embed/dashboards/'.$grant->id.'#t=');
});

it('refuses a customer user who is not on the pivot', function () {
    [$customer, $subscription, $grant, $user] = embedFixture();

    $this->withToken($customer->token)->postJson('/api/v1/embed/customer-dashboards/'.$grant->id, [
        'app_url' => 'https://cms.example.test',
        'email' => $user->email_address,
    ])->assertForbidden()
        ->assertJsonPath('message', 'You do not have access to this dashboard.');
});

it('refuses a dashboard that belongs to another site', function () {
    [$customer, $subscription, $grant, $user] = embedFixture();
    $grant->customerUsers()->attach($user->id);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://other.example.test',
    ]);

    $this->withToken($customer->token)->postJson('/api/v1/embed/customer-dashboards/'.$grant->id, [
        'app_url' => 'https://other.example.test',
        'email' => $user->email_address,
    ])->assertNotFound();
});

it('refuses a dashboard that is turned off', function () {
    [$customer, $subscription, $grant, $user] = embedFixture();
    $grant->customerUsers()->attach($user->id);
    $grant->update(['enabled' => false]);

    $this->withToken($customer->token)->postJson('/api/v1/embed/customer-dashboards/'.$grant->id, [
        'app_url' => 'https://cms.example.test',
        'email' => $user->email_address,
    ])->assertForbidden()
        ->assertJsonPath('message', 'This dashboard is turned off.');
});

it('accepts an embed token on the report and rejects it on the rest of the api', function () {
    [, , $grant, $user] = embedFixture();
    $grant->customerUsers()->attach($user->id);
    $plain = $user->createToken('aims-embed', ['embed-dashboard:'.$grant->id])->plainTextToken;

    $this->mock(TenantConnectionFactory::class, function ($mock) {
        $mock->shouldReceive('connect')->once()->andThrow(new TenantDatabaseException('The customer database could not be reached.'));
        $mock->shouldReceive('disconnect');
    });

    $this->withToken($plain)
        ->getJson('/api/backend/customer-dashboards/'.$grant->id.'/report/me')
        ->assertStatus(503)
        ->assertJsonPath('error', 'The customer database could not be reached.');

    $this->withToken($plain)->getJson('/api/backend/customers')->assertUnauthorized();
    expect($user->tokens()->where('name', 'aims-embed')->count())->toBe(1);
});

function embedFixture(): array
{
    $customer = Customer::factory()->create(['token' => 'cms-token']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.example.test',
        'database_name' => 'site_db',
        'database_user' => 'site_db',
        'database_password' => 'secret',
    ]);
    $dashboard = Dashboard::factory()->create([
        'name' => 'AIMS Cases',
        'handler' => Dashboard::HANDLER_AIMS_CASES,
        'subscription_type_id' => 1,
    ]);
    $grant = CustomerDashboard::factory()->create([
        'customer_id' => $customer->id,
        'dashboard_id' => $dashboard->id,
        'customer_subscription_id' => $subscription->id,
        'enabled' => true,
    ]);
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'is_system_admin' => false,
        'console_access' => false,
        'firearm_access' => false,
        'responder_access' => false,
        'reporter_access' => false,
        'security_access' => false,
        'driver_access' => false,
        'survey_access' => false,
        'time_and_attendance_access' => false,
        'stock_access' => false,
        'lms_access' => false,
        'precase_access' => false,
    ]);

    return [$customer, $subscription, $grant, $user];
}
