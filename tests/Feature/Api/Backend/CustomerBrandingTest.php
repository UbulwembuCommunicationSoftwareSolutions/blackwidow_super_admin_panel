<?php

use App\Jobs\PushBrandingToTenantsJob;
use App\Models\Customer;
use App\Models\CustomerBrandSlot;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
});

it('uploads customer branding media assigns default and pushes to inheriting cms subscription', function () {
    actingAsBackendUser();

    SubscriptionType::query()->where('id', 1)->delete();
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS', 'project_type' => 'php']);

    $customer = Customer::factory()->create(['token' => 'brand-api-token']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-brand-api.example.test',
    ]);

    $upload = $this->post("/api/backend/customers/{$customer->id}/branding-media", [
        'file' => UploadedFile::fake()->image('login.png'),
        'slot' => 'login_logo',
    ])->assertCreated();

    $mediaId = $upload->json('data.id');
    expect($mediaId)->not->toBeNull();

    $slot = CustomerBrandSlot::query()
        ->where('customer_id', $customer->id)
        ->where('slot', 'login_logo')
        ->first();

    expect($slot?->customer_branding_media_id)->toBe($mediaId)
        ->and($subscription->fresh()->logo_1)->not->toBeNull()
        ->and($subscription->fresh()->logo_1_checksum)->toStartWith('sha256:');

    Queue::assertPushed(PushBrandingToTenantsJob::class, function (PushBrandingToTenantsJob $job) use ($subscription) {
        return $job->subscriptionId === $subscription->id && in_array('login_logo', $job->cmsSlots, true);
    });
});

it('applies subscription override inherit and assign actions', function () {
    actingAsBackendUser();

    SubscriptionType::query()->where('id', 1)->delete();
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS', 'project_type' => 'php']);

    $customer = Customer::factory()->create(['token' => 'override-token']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-override.example.test',
    ]);

    $defaultUpload = $this->post("/api/backend/customers/{$customer->id}/branding-media", [
        'file' => UploadedFile::fake()->image('default.png'),
        'slot' => 'login_logo',
    ])->assertCreated();

    $defaultMediaId = $defaultUpload->json('data.id');

    $overrideUpload = $this->post("/api/backend/customers/{$customer->id}/branding-media", [
        'file' => UploadedFile::fake()->image('override.png'),
    ])->assertCreated();

    $overrideMediaId = $overrideUpload->json('data.id');

    $this->putJson("/api/backend/customer-subscriptions/{$subscription->id}/brand-slots/login_logo", [
        'action' => 'assign',
        'customer_branding_media_id' => $overrideMediaId,
    ])->assertOk()
        ->assertJsonPath('data.source', 'override');

    expect($subscription->fresh()->logo_1)->not->toBeNull();

    $this->putJson("/api/backend/customer-subscriptions/{$subscription->id}/brand-slots/login_logo", [
        'action' => 'inherit',
    ])->assertOk()
        ->assertJsonPath('data.source', 'inherited');

    expect(
        CustomerBrandSlot::query()
            ->where('customer_id', $customer->id)
            ->where('slot', 'login_logo')
            ->value('customer_branding_media_id'),
    )->toBe($defaultMediaId);

    Queue::assertPushed(PushBrandingToTenantsJob::class);
});
