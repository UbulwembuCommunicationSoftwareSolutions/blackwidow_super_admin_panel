<?php

use App\Models\Customer;
use App\Models\User;
use App\Models\UserCustomer;

it('rejects user-customers without a token', function () {
    $this->getJson('/api/backend/user-customers')->assertUnauthorized();
});

it('forbids user-customers without Shield permissions', function () {
    actingAsBackendForbidden();

    $this->getJson('/api/backend/user-customers')->assertForbidden();
});

it('returns 404 for a missing user-customer', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/user-customers/999999')->assertNotFound();
});

it('validates user-customer create', function () {
    actingAsBackendUser();

    $this->postJson('/api/backend/user-customers', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id', 'customer_id']);
});

it('can crud restore and force delete a user-customer', function () {
    actingAsBackendUser();
    $user = User::factory()->create();
    $customer = Customer::factory()->create();

    $create = $this->postJson('/api/backend/user-customers', [
        'user_id' => $user->id,
        'customer_id' => $customer->id,
    ])->assertCreated();

    $id = $create->json('data.id');

    $this->getJson("/api/backend/user-customers?user_id={$user->id}&customer_id={$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $id);

    $otherCustomer = Customer::factory()->create();
    $this->putJson("/api/backend/user-customers/{$id}", [
        'customer_id' => $otherCustomer->id,
    ])->assertOk()->assertJsonPath('data.customer_id', $otherCustomer->id);

    $this->deleteJson("/api/backend/user-customers/{$id}")->assertOk();
    $this->assertSoftDeleted('user_customers', ['id' => $id]);

    $this->postJson("/api/backend/user-customers/{$id}/restore")->assertOk();
    expect(UserCustomer::query()->find($id))->not->toBeNull();

    $this->deleteJson("/api/backend/user-customers/{$id}")->assertOk();
    $this->deleteJson("/api/backend/user-customers/{$id}/force")->assertOk();
    $this->assertDatabaseMissing('user_customers', ['id' => $id]);
});
