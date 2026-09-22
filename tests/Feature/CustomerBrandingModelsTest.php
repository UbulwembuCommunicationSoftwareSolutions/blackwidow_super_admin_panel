<?php

use App\Models\Customer;
use App\Models\CustomerBrandingMedia;
use App\Models\CustomerBrandSlot;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionBrandSlot;
use App\Support\BrandingSync\BrandingSyncPayload;
use App\Support\BrandingSync\CustomerBrandSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('creates default customer brand slots when a customer is created', function () {
    $customer = Customer::factory()->create();

    foreach (BrandingSyncPayload::SLOTS as $slot) {
        expect(CustomerBrandSlot::query()->where('customer_id', $customer->id)->where('slot', $slot)->exists())->toBeTrue();
    }
});

it('backfills default brand slots via ensureDefaults', function () {
    $customer = Customer::factory()->createQuietly();

    CustomerBrandSlots::ensureDefaults($customer);

    expect($customer->brandSlots()->count())->toBe(count(BrandingSyncPayload::SLOTS));
});

it('resolves effective branding media from customer and subscription overrides', function () {
    $customer = Customer::factory()->create();
    $subscription = CustomerSubscription::factory()->create(['customer_id' => $customer->id]);

    $customerMedia = CustomerBrandingMedia::factory()->create(['customer_id' => $customer->id]);
    $customer->brandSlots()->where('slot', 'login_logo')->update([
        'customer_branding_media_id' => $customerMedia->id,
    ]);

    expect($subscription->fresh()->effectiveBrandingMedia('login_logo')?->id)->toBe($customerMedia->id);

    $overrideMedia = CustomerBrandingMedia::factory()->create(['customer_id' => $customer->id]);
    CustomerSubscriptionBrandSlot::factory()->create([
        'customer_subscription_id' => $subscription->id,
        'slot' => 'login_logo',
        'customer_branding_media_id' => $overrideMedia->id,
        'is_override' => true,
        'cleared' => false,
    ]);

    expect($subscription->fresh()->effectiveBrandingMedia('login_logo')?->id)->toBe($overrideMedia->id);

    CustomerSubscriptionBrandSlot::query()
        ->where('customer_subscription_id', $subscription->id)
        ->where('slot', 'login_logo')
        ->update(['cleared' => true, 'customer_branding_media_id' => null]);

    expect($subscription->fresh()->effectiveBrandingMedia('login_logo'))->toBeNull();
});
