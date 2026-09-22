<?php

use App\Jobs\PushBrandingToTenantsJob;
use App\Models\Customer;
use App\Models\CustomerBrandingMedia;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionBrandSlot;
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
    $override = $fresh->brandSlots()->where('slot', 'login_logo')->first();
    expect($override)->not->toBeNull()
        ->and($override->is_override)->toBeTrue()
        ->and($override->cleared)->toBeFalse()
        ->and($override->media?->checksum)->toBe($checksum);
});

it('returns stale when the inbound record is older', function () {
    ['subscription' => $subscription, 'customer' => $customer] = brandingTenant();
    $media = CustomerBrandingMedia::factory()
        ->for($customer)
        ->withPngFile(pngBytes())
        ->create();
    CustomerSubscriptionBrandSlot::factory()->create([
        'customer_subscription_id' => $subscription->id,
        'slot' => 'login_logo',
        'customer_branding_media_id' => $media->id,
        'is_override' => true,
        'cleared' => false,
        'updated_at' => now(),
    ]);

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

    expect($subscription->fresh()->effectiveBrandingMedia('login_logo')?->id)->toBe($media->id);
});

it('clears a slot when cleared is true', function () {
    ['subscription' => $subscription, 'customer' => $customer] = brandingTenant();
    $media = CustomerBrandingMedia::factory()->for($customer)->withPngFile()->create();
    CustomerSubscriptionBrandSlot::factory()->create([
        'customer_subscription_id' => $subscription->id,
        'slot' => 'login_logo',
        'customer_branding_media_id' => $media->id,
        'is_override' => true,
        'cleared' => false,
    ]);

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

    $slot = $subscription->fresh()->brandSlots()->where('slot', 'login_logo')->first();
    expect($slot?->cleared)->toBeTrue()
        ->and($slot?->customer_branding_media_id)->toBeNull();
});

it('returns unchanged when checksum matches', function () {
    ['subscription' => $subscription, 'customer' => $customer] = brandingTenant();
    $checksum = BrandingSyncPayload::computeChecksum(pngBytes());
    $media = CustomerBrandingMedia::factory()
        ->for($customer)
        ->create(['checksum' => $checksum]);
    $media->addMediaFromString(pngBytes())->usingFileName('same.png')->toMediaCollection('file');
    CustomerSubscriptionBrandSlot::factory()->create([
        'customer_subscription_id' => $subscription->id,
        'slot' => 'login_logo',
        'customer_branding_media_id' => $media->id,
        'is_override' => true,
        'cleared' => false,
    ]);

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
    ['subscription' => $subscription, 'customer' => $customer] = brandingTenant();
    $media = CustomerBrandingMedia::factory()->for($customer)->withPngFile()->create();
    CustomerSubscriptionBrandSlot::factory()->create([
        'customer_subscription_id' => $subscription->id,
        'slot' => 'login_logo',
        'customer_branding_media_id' => $media->id,
        'is_override' => true,
        'cleared' => false,
    ]);

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
