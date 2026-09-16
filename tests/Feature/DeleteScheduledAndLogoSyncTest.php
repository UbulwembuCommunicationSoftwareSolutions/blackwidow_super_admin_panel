<?php

use App\Jobs\PushBrandingToTenantsJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Http::fake();
});

function tombstoneCustomerSetup(): array
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
        'email_address' => 'tombstone@example.test',
        'password' => 'secret-password',
        'console_access' => true,
        'skip_sync' => true,
    ]);

    return compact('customer', 'subscription', 'user');
}

it('includes trashed users with delete_scheduled in user-import', function () {
    ['subscription' => $subscription, 'user' => $user] = tombstoneCustomerSetup();

    $user->scheduleDelete();

    $response = $this->withToken('test-token')->postJson('/api/user-import', [
        'app_url' => $subscription->url,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $imported = collect($response->json('data'));
    $row = $imported->firstWhere('id', $user->id);

    expect($row)->not->toBeNull()
        ->and($row['delete_scheduled'])->not->toBeNull()
        ->and($row['email_address'])->toBe('tombstone@example.test');
});

it('sets delete_scheduled when archiving via archive-user', function () {
    ['subscription' => $subscription, 'user' => $user] = tombstoneCustomerSetup();

    $response = $this->withToken('test-token')->postJson('/api/archive-user', [
        'app_url' => $subscription->url,
        'email' => $user->email_address,
    ]);

    $response->assertSuccessful();

    $fresh = CustomerUser::withTrashed()->find($user->id);
    expect($fresh->trashed())->toBeTrue()
        ->and($fresh->delete_scheduled)->not->toBeNull();
});

it('clears delete_scheduled when restoring via restore-user', function () {
    ['subscription' => $subscription, 'user' => $user] = tombstoneCustomerSetup();
    $user->scheduleDelete();

    $response = $this->withToken('test-token')->postJson('/api/restore-user', [
        'app_url' => $subscription->url,
        'super_admin_user_id' => $user->id,
    ]);

    $response->assertSuccessful();

    $fresh = CustomerUser::find($user->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->delete_scheduled)->toBeNull()
        ->and($fresh->trashed())->toBeFalse();
});

it('resurrects tombstoned user on create-user with same email', function () {
    ['subscription' => $subscription, 'user' => $user] = tombstoneCustomerSetup();
    $user->scheduleDelete();

    $response = $this->withToken('test-token')->postJson('/api/create-user', [
        'app_url' => $subscription->url,
        'password' => 'new-secret-password',
        'user' => [
            'first_name' => 'Resurrected',
            'last_name' => 'User',
            'email' => $user->email_address,
            'console_access' => true,
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('user.id', $user->id);

    $fresh = CustomerUser::find($user->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->first_name)->toBe('Resurrected')
        ->and($fresh->delete_scheduled)->toBeNull();
});

it('rejects login when delete_scheduled is set', function () {
    ['subscription' => $subscription, 'user' => $user] = tombstoneCustomerSetup();
    $user->scheduleDelete();

    $response = $this->postJson('/api/user-login', [
        'app_url' => $subscription->url,
        'email' => $user->email_address,
        'password' => 'secret-password',
    ]);

    $response->assertUnauthorized();
});

it('dispatches PushBrandingToTenantsJob when uploading logos on a CMS subscription', function () {
    Storage::fake('public');

    $customer = Customer::factory()->create(['token' => 'logo-token']);
    SubscriptionType::query()->where('id', 1)->delete();
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS', 'project_type' => 'php']);

    $row = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-logos.example.test',
    ]);

    actingAsBackendUser();

    $this->post("/api/backend/customer-subscriptions/{$row->id}/logos", [
        'logo_1' => UploadedFile::fake()->image('login.png'),
    ])->assertOk();

    Queue::assertPushed(PushBrandingToTenantsJob::class, function ($job) use ($row) {
        return $job->subscriptionId === $row->id && in_array('login_logo', $job->cmsSlots, true);
    });

    expect($row->fresh()->logo_1_updated_at)->not->toBeNull();
});

it('includes logo timestamps on customer_logos response', function () {
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $customer = Customer::factory()->create();
    $updatedAt = now()->subMinutes(5);

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-ts.example.test',
        'logo_1' => 'brand/login.png',
        'logo_1_updated_at' => $updatedAt,
    ]);

    $response = $this->get('/customer_logos?customer_url='.urlencode('https://cms-ts.example.test'));

    $response->assertSuccessful();
    expect($response->json('logo_1_updated_at'))->not->toBeNull();
});
