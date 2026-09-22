<?php

use App\Console\Commands\BrandingBackfillCustomerMediaCommand;
use App\Jobs\PushBrandingToTenantsJob;
use App\Models\Customer;
use App\Models\CustomerBrandingMedia;
use App\Models\CustomerBrandSlot;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionBrandSlot;
use App\Models\SubscriptionType;
use App\Services\BrandingSync\TenantBrandingPusher;
use App\Support\BrandingSync\BrandingSyncPayload;
use App\Support\BrandingSync\CustomerBrandSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['services.lms.sync_token' => 'lms-sync-token']);
});

function pngPayloadBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

it('builds fromSubscription payload from effective branding media', function () {
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create(['customer_id' => $customer->id]);
    $media = CustomerBrandingMedia::factory()->for($customer)->withPngFile()->create();

    $customer->brandSlots()->where('slot', 'login_logo')->update([
        'customer_branding_media_id' => $media->id,
    ]);

    $payload = BrandingSyncPayload::fromSubscription($subscription->fresh(), 'login_logo');

    expect($payload->checksum)->toBe($media->checksum)
        ->and($payload->url)->not->toBeNull()
        ->and($payload->cleared)->toBeFalse();
});

it('falls back to legacy logo columns when brand slots have no media', function () {
    $customer = Customer::factory()->createQuietly();
    CustomerBrandSlots::ensureDefaults($customer);
    Storage::disk('public')->put('legacy-login.png', pngPayloadBytes());
    $checksum = BrandingSyncPayload::computeChecksum(pngPayloadBytes());

    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'logo_1' => 'legacy-login.png',
        'logo_1_checksum' => $checksum,
        'logo_1_updated_at' => now(),
    ]);

    $payload = BrandingSyncPayload::fromSubscription($subscription, 'login_logo');

    expect($payload->checksum)->toBe($checksum)
        ->and($payload->cleared)->toBeFalse();
});

it('pushes customer default branding to the LMS hub', function () {
    SubscriptionType::factory()->create(['id' => SubscriptionType::LMS_TYPE_ID, 'name' => 'LMS']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);

    $customer = Customer::factory()->create(['token' => 'tenant-token']);
    $media = CustomerBrandingMedia::factory()->for($customer)->withPngFile()->create();
    CustomerBrandSlot::query()
        ->where('customer_id', $customer->id)
        ->where('slot', 'login_logo')
        ->update(['customer_branding_media_id' => $media->id]);

    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => SubscriptionType::LMS_TYPE_ID,
        'url' => 'https://lms-hub.example.test',
    ]);

    Http::fake([
        'https://lms-hub.example.test/admin-api/v1/sync/branding' => Http::response(['success' => true], 200),
    ]);

    config(['branding_sync.enabled' => true, 'branding_sync.lms_hub_enabled' => true]);

    app(TenantBrandingPusher::class)->pushCustomerDefaultsToLmsHub($customer, ['login_logo']);

    Http::assertSent(function ($request) use ($customer, $media) {
        $body = $request->data();

        return $request->url() === 'https://lms-hub.example.test/admin-api/v1/sync/branding'
            && ($body['branding']['super_admin_customer_id'] ?? null) === $customer->id
            && ($body['branding']['checksum'] ?? null) === $media->checksum
            && $request->hasHeader('Authorization', 'Bearer lms-sync-token');
    });
});

it('dispatches customer branding job for customer brand slot updates', function () {
    Queue::fake();

    $customer = Customer::factory()->create();
    $media = CustomerBrandingMedia::factory()->for($customer)->withPngFile()->create();
    $slot = $customer->brandSlots()->where('slot', 'login_logo')->first();

    $slot->update(['customer_branding_media_id' => $media->id]);

    Queue::assertPushed(PushBrandingToTenantsJob::class, function (PushBrandingToTenantsJob $job) use ($customer) {
        return $job->customerId === $customer->id
            && in_array('login_logo', $job->cmsSlots, true);
    });
});

it('backfill command creates media and subscription overrides idempotently', function () {
    Queue::fake();
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $customer = Customer::factory()->create();
    Storage::disk('public')->put('subs/login.png', pngPayloadBytes());
    $checksum = BrandingSyncPayload::computeChecksum(pngPayloadBytes());

    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'logo_1' => 'subs/login.png',
        'logo_1_checksum' => $checksum,
        'logo_1_updated_at' => now(),
    ]);

    Artisan::call(BrandingBackfillCustomerMediaCommand::class);

    expect(CustomerBrandingMedia::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(
            CustomerSubscriptionBrandSlot::query()
                ->where('customer_subscription_id', $subscription->id)
                ->where('slot', 'login_logo')
                ->value('customer_branding_media_id')
        )->not->toBeNull();

    Artisan::call(BrandingBackfillCustomerMediaCommand::class);

    expect(CustomerBrandingMedia::query()->where('customer_id', $customer->id)->count())->toBe(1);
});
