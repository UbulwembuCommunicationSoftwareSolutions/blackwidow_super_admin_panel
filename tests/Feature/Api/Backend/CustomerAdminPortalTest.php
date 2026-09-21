<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Http::fake();
    config(['user_sync.enabled' => false]);
});

it('scopes a customer admin to their own customer show page', function () {
    $own = Customer::factory()->create([
        'company_name' => 'Own Portal Co',
        'mail_host' => 'smtp.own.test',
        'mail_username' => 'own-user',
    ]);
    $other = Customer::factory()->create(['company_name' => 'Other Portal Co']);
    actingAsCustomerAdmin($own);

    $this->getJson("/api/backend/customers/{$own->id}")
        ->assertOk()
        ->assertJsonPath('data.company_name', 'Own Portal Co')
        ->assertJsonMissingPath('data.token')
        ->assertJsonMissingPath('data.mail_host')
        ->assertJsonMissingPath('data.mail_username');

    $this->getJson("/api/backend/customers/{$other->id}")->assertForbidden();
    $this->getJson('/api/backend/customers')->assertForbidden();
    $this->getJson("/api/backend/customers/{$own->id}/credentials")->assertForbidden();
    $this->patchJson("/api/backend/customers/{$own->id}", ['company_name' => 'Renamed'])->assertForbidden();
    $this->getJson('/api/backend/user-customers')->assertForbidden();
});

it('lets a customer admin manage only their own client users', function () {
    $own = Customer::factory()->create();
    $other = Customer::factory()->create();
    $admin = actingAsCustomerAdmin($own, ['email_address' => 'portal-admin@example.com']);

    $otherUser = CustomerUser::factory()->create([
        'customer_id' => $other->id,
        'email_address' => 'other-user@example.com',
        'is_system_admin' => false,
        'skip_sync' => true,
    ]);
    $member = CustomerUser::factory()->create([
        'customer_id' => $own->id,
        'email_address' => 'member@example.com',
        'is_system_admin' => false,
        'skip_sync' => true,
    ]);

    $listed = collect($this->getJson('/api/backend/customer-users')->assertOk()->json('data'))
        ->pluck('id');
    expect($listed->contains($admin->id))->toBeTrue()
        ->and($listed->contains($member->id))->toBeTrue()
        ->and($listed->contains($otherUser->id))->toBeFalse();

    $this->putJson("/api/backend/customer-users/{$otherUser->id}", ['first_name' => 'Hijack'])
        ->assertForbidden();

    $created = $this->postJson('/api/backend/customer-users', [
        'customer_id' => $other->id,
        'first_name' => 'New',
        'last_name' => 'User',
        'email_address' => 'new-user@example.com',
        'password' => 'secret-pass',
    ])->assertCreated();

    expect($created->json('data.customer_id'))->toBe($own->id);

    $this->postJson('/api/backend/customer-users', [
        'customer_id' => $own->id,
        'first_name' => 'Elevated',
        'last_name' => 'User',
        'email_address' => 'elevated@example.com',
        'password' => 'secret-pass',
        'is_system_admin' => true,
    ])->assertForbidden();

    $this->putJson("/api/backend/customer-users/{$member->id}", [
        'is_system_admin' => true,
    ])->assertForbidden();

    expect($member->fresh()->is_system_admin)->toBeFalse();

    $this->putJson("/api/backend/customer-users/{$member->id}", [
        'first_name' => 'Updated',
    ])->assertOk()->assertJsonPath('data.first_name', 'Updated');
});

it('lets a customer admin view only their own subscriptions', function () {
    $own = Customer::factory()->create();
    $other = Customer::factory()->create();
    $type = SubscriptionType::factory()->create();
    actingAsCustomerAdmin($own);

    $ownSub = CustomerSubscription::factory()->create([
        'customer_id' => $own->id,
        'subscription_type_id' => $type->id,
        'url' => 'https://own-portal.test',
    ]);
    $otherSub = CustomerSubscription::factory()->create([
        'customer_id' => $other->id,
        'subscription_type_id' => $type->id,
        'url' => 'https://other-portal.test',
    ]);

    $ids = collect($this->getJson('/api/backend/customer-subscriptions')->assertOk()->json('data'))
        ->pluck('id');
    expect($ids->contains($ownSub->id))->toBeTrue()
        ->and($ids->contains($otherSub->id))->toBeFalse();

    $this->getJson("/api/backend/customer-subscriptions/{$otherSub->id}")->assertForbidden();
    $this->postJson('/api/backend/customer-subscriptions', [])->assertForbidden();
    $this->patchJson("/api/backend/customer-subscriptions/{$ownSub->id}", [
        'panic_button_enabled' => true,
    ])->assertForbidden();
    $this->postJson("/api/backend/customer-subscriptions/{$ownSub->id}/deploy")->assertForbidden();
});

it('keeps other customers out of search results', function () {
    $own = Customer::factory()->create(['company_name' => 'Own Holdings']);
    $other = Customer::factory()->create(['company_name' => 'Foreign Holdings']);
    $type = SubscriptionType::factory()->create();
    actingAsCustomerAdmin($own);

    CustomerSubscription::factory()->create([
        'customer_id' => $other->id,
        'subscription_type_id' => $type->id,
        'url' => 'https://foreign-holdings.test',
    ]);

    $groups = collect($this->getJson('/api/backend/search?q=Holdings')->assertOk()->json('data'));
    $titles = $groups->flatMap(fn (array $group) => collect($group['results'])->pluck('title'));

    expect($groups->pluck('type')->all())->not->toContain('customer')
        ->and($titles->contains(fn (string $title) => str_contains($title, 'foreign-holdings')))->toBeFalse()
        ->and($titles->contains(fn (string $title) => str_contains($title, 'Foreign Holdings')))->toBeFalse();
});

it('revokes portal access when system admin is turned off', function () {
    $customer = Customer::factory()->create();
    $admin = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'revoke@example.com',
        'password' => 'secret-pass',
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);

    $token = $this->postJson('/api/backend/login', [
        'email' => 'revoke@example.com',
        'password' => 'secret-pass',
    ])->assertOk()->json('data.token');

    $admin->update(['is_system_admin' => false]);

    $this->withToken($token)->getJson('/api/backend/user')->assertUnauthorized();

    $this->postJson('/api/backend/login', [
        'email' => 'revoke@example.com',
        'password' => 'secret-pass',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);
});
