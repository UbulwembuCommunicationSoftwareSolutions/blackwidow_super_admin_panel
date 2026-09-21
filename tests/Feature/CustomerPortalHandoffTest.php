<?php

use App\Models\Customer;
use App\Models\CustomerUser;

it('hands a system admin cookie to the customer portal', function () {
    config(['sso.customer_portal_url' => 'https://portal.test']);

    $customer = Customer::factory()->create();
    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'is_system_admin' => true,
        'skip_sync' => true,
        'password' => 'secret-pass',
    ]);
    $plain = $user->createToken('customer-user-token')->plainTextToken;

    $response = $this->withUnencryptedCookie('external_token', $plain)
        ->get('/customer-portal');

    $location = (string) $response->headers->get('Location');
    expect($location)->toStartWith('https://portal.test/customer?sso=');

    $code = substr($location, strlen('https://portal.test/customer?sso='));

    $this->postJson('/api/backend/login/sso', ['code' => $code])
        ->assertSuccessful()
        ->assertJsonPath('data.token', $plain)
        ->assertJsonPath('data.user.actor_type', 'customer_admin')
        ->assertJsonPath('data.user.customer_id', $customer->id);

    $this->postJson('/api/backend/login/sso', ['code' => $code])
        ->assertUnprocessable();
});

it('rejects a cookie that is not a system admin', function () {
    config(['sso.customer_portal_url' => 'https://portal.test']);

    $this->withUnencryptedCookie('external_token', 'missing')
        ->get('/customer-portal')
        ->assertRedirect('https://portal.test/customer?error=unavailable');
});
