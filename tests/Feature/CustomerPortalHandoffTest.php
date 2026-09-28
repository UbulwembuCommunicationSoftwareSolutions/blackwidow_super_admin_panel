<?php

use App\Models\Customer;
use App\Models\CustomerUser;
use App\Support\Sso\SsoHandoffLog;
use Illuminate\Support\Facades\Log;

it('hands a system admin cookie to the customer portal', function () {
    Log::spy();
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

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REDIRECTED
        && $context['handoff'] === 'customer_portal'
        && $context['customer_user_id'] === $user->id
        && $context['redirect_host'] === 'portal.test'
        && ! str_contains(json_encode($context), $code));

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REDIRECTED
        && $context['handoff'] === 'customer_portal_exchange'
        && $context['customer_user_id'] === $user->id);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REFUSED
        && $context['handoff'] === 'customer_portal_exchange'
        && $context['reason'] === 'code_expired');
});

it('rejects a cookie that is not a system admin', function () {
    Log::spy();
    config(['sso.customer_portal_url' => 'https://portal.test']);

    $this->withUnencryptedCookie('external_token', 'missing')
        ->get('/customer-portal')
        ->assertRedirect('https://portal.test/customer?error=unavailable');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REFUSED
        && $context['handoff'] === 'customer_portal'
        && $context['reason'] === 'token_not_found'
        && $context['has_cookie'] === true);
});

it('logs a customer user who is not a system admin', function () {
    Log::spy();
    config(['sso.customer_portal_url' => 'https://portal.test']);

    $user = CustomerUser::factory()->create([
        'customer_id' => Customer::factory()->create()->id,
        'is_system_admin' => false,
        'skip_sync' => true,
        'password' => 'secret-pass',
    ]);
    $plain = $user->createToken('customer-user-token')->plainTextToken;

    $this->withUnencryptedCookie('external_token', $plain)
        ->get('/customer-portal')
        ->assertRedirect('https://portal.test/customer?error=unavailable');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === SsoHandoffLog::REFUSED
        && $context['reason'] === 'not_system_admin'
        && $context['customer_user_id'] === $user->id);
});
