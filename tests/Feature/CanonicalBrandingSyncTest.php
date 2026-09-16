<?php

use App\Jobs\PushBrandingToTenantsJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
});

function brandingTenant(): array
{
    $customer = Customer::factory()->create(['token' => 'branding-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-branding.example.test',
        'logo_1' => null,
        'logo_2' => null,
        'logo_3' => null,
        'logo_1_checksum' => null,
        'logo_2_checksum' => null,
        'logo_3_checksum' => null,
        'logo_1_updated_at' => null,
        'logo_2_updated_at' => null,
        'logo_3_updated_at' => null,
    ]);

    return compact('customer', 'subscription');
}

function pngBytes(): string
{
    // Minimal valid 1x1 PNG
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

it('creates branding from a tenant push and stores the file', function () {
    ['subscription' => $subscription] = brandingTenant();
    $bytes = pngBytes();
    $checksum = BrandingSyncPayload::computeChecksum($bytes);

    Http::fake([
        'https://cms-branding.example.test/storage/login.png' => Http::response($bytes, 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->withToken('branding-token')->postJson('/api/v1/sync/branding', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'branding' => [
            'slot' => 'login_logo',
            'url' => 'https://cms-branding.example.test/storage/login.png',
            'checksum' => $checksum,
            'cleared' => false,
            'updated_at' => now()->toIso8601String(),
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('outcome', 'created')
        ->assertJsonPath('branding.slot', 'login_logo');

    $fresh = $subscription->fresh();
    expect($fresh->logo_1)->not->toBeNull()
        ->and($fresh->logo_1_checksum)->toBe($checksum)
        ->and($fresh->logo_1_updated_at)->not->toBeNull();
});

it('returns stale when the inbound record is older', function () {
    ['subscription' => $subscription] = brandingTenant();
    Storage::disk('public')->put('existing.png', pngBytes());
    $subscription->forceFill([
        'logo_1' => 'existing.png',
        'logo_1_updated_at' => now(),
        'logo_1_checksum' => BrandingSyncPayload::computeChecksum(pngBytes()),
    ])->saveQuietly();

    $olderBytes = pngBytes().'different';
    Http::fake([
        'https://cms-branding.example.test/storage/older.png' => Http::response($olderBytes, 200),
    ]);

    $response = $this->withToken('branding-token')->postJson('/api/v1/sync/branding', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'branding' => [
            'slot' => 'login_logo',
            'url' => 'https://cms-branding.example.test/storage/older.png',
            'checksum' => BrandingSyncPayload::computeChecksum($olderBytes),
            'cleared' => false,
            'updated_at' => now()->subHour()->toIso8601String(),
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('outcome', 'stale');

    expect($subscription->fresh()->logo_1)->toBe('existing.png');
});

it('clears a slot when cleared is true', function () {
    ['subscription' => $subscription] = brandingTenant();
    Storage::disk('public')->put('to-clear.png', pngBytes());
    $subscription->forceFill([
        'logo_1' => 'to-clear.png',
        'logo_1_updated_at' => now()->subHour(),
        'logo_1_checksum' => 'sha256:abc',
    ])->saveQuietly();

    $response = $this->withToken('branding-token')->postJson('/api/v1/sync/branding', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'branding' => [
            'slot' => 'login_logo',
            'url' => null,
            'checksum' => null,
            'cleared' => true,
            'updated_at' => now()->toIso8601String(),
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('outcome', 'cleared')
        ->assertJsonPath('branding.cleared', true);

    expect($subscription->fresh()->logo_1)->toBeNull()
        ->and($subscription->fresh()->logo_1_checksum)->toBeNull();
});

it('returns unchanged when checksum matches', function () {
    ['subscription' => $subscription] = brandingTenant();
    $checksum = BrandingSyncPayload::computeChecksum(pngBytes());
    Storage::disk('public')->put('same.png', pngBytes());
    $subscription->forceFill([
        'logo_1' => 'same.png',
        'logo_1_updated_at' => now()->subDay(),
        'logo_1_checksum' => $checksum,
    ])->saveQuietly();

    $response = $this->withToken('branding-token')->postJson('/api/v1/sync/branding', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'branding' => [
            'slot' => 'login_logo',
            'url' => 'https://cms-branding.example.test/storage/same.png',
            'checksum' => $checksum,
            'cleared' => false,
            'updated_at' => now()->toIso8601String(),
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('outcome', 'unchanged');
});

it('lists all branding slots for reconciliation', function () {
    ['subscription' => $subscription] = brandingTenant();
    Storage::disk('public')->put('login.png', pngBytes());
    $subscription->forceFill([
        'logo_1' => 'login.png',
        'logo_1_updated_at' => now(),
        'logo_1_checksum' => BrandingSyncPayload::computeChecksum(pngBytes()),
    ])->saveQuietly();

    $response = $this->withToken('branding-token')->getJson(
        '/api/v1/sync/branding?app_url='.urlencode($subscription->url)
    );

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($response->json('branding'))->toHaveCount(3)
        ->and(collect($response->json('branding'))->pluck('slot')->all())
        ->toBe(BrandingSyncPayload::SLOTS);
});

it('requires bearer auth for branding sync', function () {
    ['subscription' => $subscription] = brandingTenant();

    $this->postJson('/api/v1/sync/branding', [
        'app_url' => $subscription->url,
        'branding' => [
            'slot' => 'login_logo',
            'cleared' => true,
            'updated_at' => now()->toIso8601String(),
        ],
    ])->assertUnauthorized();
});

it('dispatches PushBrandingToTenantsJob when uploading logos on a CMS subscription', function () {
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

    Queue::assertPushed(PushBrandingToTenantsJob::class, function (PushBrandingToTenantsJob $job) use ($row) {
        return $job->subscriptionId === $row->id && in_array('login_logo', $job->cmsSlots, true);
    });

    expect($row->fresh()->logo_1_updated_at)->not->toBeNull()
        ->and($row->fresh()->logo_1_checksum)->not->toBeNull();
});
