<?php

use App\Jobs\SendSubscriptionEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Http::fake();
    config(['services.superadmin.sync_enabled' => false]);
});

it('rejects customer users without a token', function () {
    $this->getJson('/api/backend/customer-users')->assertUnauthorized();
});

it('forbids customer users without Shield permissions', function () {
    actingAsBackendForbidden();

    $this->getJson('/api/backend/customer-users')->assertForbidden();
});

it('returns 404 for a missing customer user', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/customer-users/999999')->assertNotFound();
});

it('validates customer user create', function () {
    actingAsBackendUser();

    $this->postJson('/api/backend/customer-users', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id', 'first_name', 'last_name', 'email_address', 'password']);
});

it('can crud restore and force delete a customer user without returning the password', function () {
    actingAsBackendUser();
    $customer = Customer::factory()->create();

    $create = $this->postJson('/api/backend/customer-users', [
        'customer_id' => $customer->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email_address' => 'jane@example.com',
        'password' => 'secret1',
        'console_access' => false,
    ])->assertCreated();

    $id = $create->json('data.id');
    expect($create->json('data.first_name'))->toBe('Jane')
        ->and($create->json('data'))->not->toHaveKey('password');

    $this->getJson("/api/backend/customer-users?customer_id={$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.0.email_address', 'jane@example.com');

    $this->putJson("/api/backend/customer-users/{$id}", [
        'first_name' => 'Janet',
    ])->assertOk()->assertJsonPath('data.first_name', 'Janet');

    $this->deleteJson("/api/backend/customer-users/{$id}")
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->assertSoftDeleted('customer_users', ['id' => $id]);

    $this->postJson("/api/backend/customer-users/{$id}/restore")
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Janet');

    $this->deleteJson("/api/backend/customer-users/{$id}")->assertOk();
    $this->deleteJson("/api/backend/customer-users/{$id}/force")->assertOk();
    $this->assertDatabaseMissing('customer_users', ['id' => $id]);
});

it('updates a customer user password and access rights', function () {
    actingAsBackendUser();
    $customer = Customer::factory()->create();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'super_admin_user_id' => null,
        'console_access' => false,
    ]);

    $this->postJson("/api/backend/customer-users/{$user->id}/update-password", [
        'new_password' => 'newpass1',
        'confirm_password' => 'nope',
    ])->assertStatus(422);

    $this->postJson("/api/backend/customer-users/{$user->id}/update-password", [
        'new_password' => 'newpass1',
        'confirm_password' => 'newpass1',
    ])->assertOk();

    expect(Hash::check('newpass1', $user->fresh()->password))->toBeTrue();

    $this->putJson("/api/backend/customer-users/{$user->id}/access-rights", [
        'console_access' => true,
        'is_system_admin' => true,
    ])->assertOk()
        ->assertJsonPath('data.console_access', true)
        ->assertJsonPath('data.is_system_admin', true);
});

it('dispatches a welcome email job', function () {
    actingAsBackendUser();
    $customer = Customer::factory()->create();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'super_admin_user_id' => null,
        'console_access' => false,
    ]);

    $this->postJson("/api/backend/customer-users/{$user->id}/send-welcome-email")
        ->assertOk()
        ->assertJsonPath('ok', true);

    Queue::assertPushed(SendWelcomeEmailJob::class);
});

it('sends a login email when the user has matching access', function () {
    actingAsBackendUser();
    $customer = Customer::factory()->create();
    SubscriptionType::factory()->create();
    $firearmType = SubscriptionType::factory()->create();
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $firearmType->id,
    ]);
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'super_admin_user_id' => null,
        'console_access' => false,
        'firearm_access' => true,
        'responder_access' => false,
        'reporter_access' => false,
        'security_access' => false,
        'driver_access' => false,
        'survey_access' => false,
        'time_and_attendance_access' => false,
        'stock_access' => false,
    ]);

    expect($firearmType->id)->toBe(2);

    $this->postJson("/api/backend/customer-users/{$user->id}/send-login-email", [
        'subscription_type_id' => $firearmType->id,
    ])->assertOk();

    Queue::assertPushed(SendSubscriptionEmailJob::class);
});

it('rejects a login email when the user lacks access', function () {
    actingAsBackendUser();
    $customer = Customer::factory()->create();
    SubscriptionType::factory()->create();
    $firearmType = SubscriptionType::factory()->create();
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $firearmType->id,
    ]);
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'skip_sync' => true,
        'super_admin_user_id' => null,
        'firearm_access' => false,
    ]);

    $this->postJson("/api/backend/customer-users/{$user->id}/send-login-email", [
        'subscription_type_id' => $firearmType->id,
    ])->assertStatus(422);
});
