<?php

use App\Jobs\SendWelcomeEmailJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use App\Services\CMSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * We only ever trigger a console's welcome email — the console owns the
 * password_reset_tokens table and always sends it itself. These cover both
 * halves: the trigger call this panel makes, and the endpoint a console hits
 * when an admin resends one by hand.
 */
beforeEach(function () {
    Mail::fake();
    Queue::fake();
});

function consoleTenant(array $subscriptionAttributes = []): array
{
    $customer = Customer::factory()->create([
        'token' => 'console-token',
        'company_name' => 'Acme Holdings',
    ]);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://console.example.test',
        'app_name' => 'Console',
    ], $subscriptionAttributes));

    return compact('customer', 'subscription');
}

/**
 * Created without console_access so the observer's own welcome email job stays
 * out of these assertions; nothing under test reads the flag.
 */
function consoleUser(Customer $customer): CustomerUser
{
    return CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'reset-me@tenant.test',
        'first_name' => 'Reset',
        'last_name' => 'Me',
        'console_access' => false,
        'skip_sync' => true,
    ]);
}

it('never sends the welcome email itself, whatever the console reports', function () {
    ['customer' => $customer] = consoleTenant();
    $user = consoleUser($customer);

    Http::fake([
        '*/admin-api/send-welcome-email' => Http::response([
            'message' => 'Email sent',
            'sent_by' => 'cms',
        ], 200),
    ]);

    (new CMSService)->sendWelcomeEmail($user);

    Mail::assertNothingSent();
});

it('sends the console the user email with the customer bearer token', function () {
    ['customer' => $customer] = consoleTenant();
    $user = consoleUser($customer);

    Http::fake([
        '*/admin-api/send-welcome-email' => Http::response(['message' => 'Email sent', 'sent_by' => 'cms'], 200),
    ]);

    (new CMSService)->sendWelcomeEmail($user);

    Http::assertSent(function ($request) use ($user) {
        return $request->url() === 'https://console.example.test/admin-api/send-welcome-email'
            && $request->hasHeader('Authorization', 'Bearer console-token')
            && $request['email'] === $user->email_address;
    });
});

it('fails loudly so the job retries when the console request errors', function () {
    ['customer' => $customer] = consoleTenant();
    $user = consoleUser($customer);

    Http::fake([
        '*/admin-api/send-welcome-email' => Http::response(['message' => 'Server error'], 500),
    ]);

    expect(fn () => (new CMSService)->sendWelcomeEmail($user))
        ->toThrow(RuntimeException::class);

    Mail::assertNothingSent();
});

it('sends nothing when the customer has no console subscription', function () {
    Http::fake();
    $customer = Customer::factory()->create(['token' => 'console-token']);

    (new CMSService)->sendWelcomeEmail(consoleUser($customer));

    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('queues the email when a console asks us to resend one', function () {
    ['subscription' => $subscription, 'customer' => $customer] = consoleTenant();
    $user = consoleUser($customer);

    $this->withToken('console-token')
        ->postJson('/api/v1/sync/users/password-reset-email', [
            'app_url' => $subscription->url,
            'origin' => 'cms',
            'user' => ['email' => $user->email_address],
        ])
        ->assertAccepted()
        ->assertJsonPath('success', true)
        ->assertJsonPath('user.email', $user->email_address);

    Queue::assertPushed(SendWelcomeEmailJob::class, function (SendWelcomeEmailJob $job) use ($user) {
        return $job->customerUser->is($user);
    });
});

it('does not queue an email for a user the tenant does not own', function () {
    ['subscription' => $subscription] = consoleTenant();
    $otherCustomer = Customer::factory()->create(['token' => 'someone-else']);
    $stranger = consoleUser($otherCustomer);

    $this->withToken('console-token')
        ->postJson('/api/v1/sync/users/password-reset-email', [
            'app_url' => $subscription->url,
            'origin' => 'cms',
            'user' => ['email' => $stranger->email_address],
        ])
        ->assertNotFound()
        ->assertJsonPath('success', false);

    Queue::assertNotPushed(SendWelcomeEmailJob::class);
});

it('rejects a resend request with the wrong bearer token', function () {
    ['subscription' => $subscription, 'customer' => $customer] = consoleTenant();
    $user = consoleUser($customer);

    $this->withToken('not-the-token')
        ->postJson('/api/v1/sync/users/password-reset-email', [
            'app_url' => $subscription->url,
            'user' => ['email' => $user->email_address],
        ])
        ->assertUnauthorized();

    Queue::assertNotPushed(SendWelcomeEmailJob::class);
});

it('rejects a resend request that cannot identify the user', function () {
    ['subscription' => $subscription] = consoleTenant();

    $this->withToken('console-token')
        ->postJson('/api/v1/sync/users/password-reset-email', [
            'app_url' => $subscription->url,
            'user' => [],
        ])
        ->assertUnprocessable();

    Queue::assertNotPushed(SendWelcomeEmailJob::class);
});
