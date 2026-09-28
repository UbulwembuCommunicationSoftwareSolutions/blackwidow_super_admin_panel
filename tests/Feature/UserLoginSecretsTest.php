<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Http::fake();
    Log::spy();
});

/**
 * @return array{subscription: CustomerSubscription, user: CustomerUser}
 */
function loginSecretsSetup(): array
{
    $customer = Customer::factory()->create(['token' => 'test-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms.example.test',
    ]);

    $user = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'secrets@example.test',
        'password' => 'correct-password',
        'console_access' => true,
        'skip_sync' => true,
    ]);

    return compact('subscription', 'user');
}

function assertNothingLoggedContaining(string $needle): void
{
    foreach (['info', 'warning', 'debug', 'error'] as $level) {
        Log::shouldNotHaveReceived($level, fn (...$args): bool => str_contains(json_encode($args), $needle));
    }
}

it('does not echo the password or stored hash on a failed login', function () {
    ['subscription' => $subscription, 'user' => $user] = loginSecretsSetup();

    $response = $this->postJson('/api/user-login', [
        'app_url' => $subscription->url,
        'email' => $user->email_address,
        'password' => 'wrong-password',
    ]);

    $response->assertUnauthorized()->assertJsonMissingPath('debug');

    expect($response->getContent())->not->toContain('wrong-password');

    assertNothingLoggedContaining('wrong-password');
    assertNothingLoggedContaining($user->password);
});

it('does not log the password on a successful login', function () {
    ['subscription' => $subscription, 'user' => $user] = loginSecretsSetup();

    $this->postJson('/api/user-login', [
        'app_url' => $subscription->url,
        'email' => $user->email_address,
        'password' => 'correct-password',
    ])->assertSuccessful();

    assertNothingLoggedContaining('correct-password');
    assertNothingLoggedContaining($user->password);
});
