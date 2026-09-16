<?php

use App\Jobs\PushBrandingToTenantsJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
    Http::fake([
        '*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='), 200),
    ]);
});

it('does not echo an inbound branding upsert back to the tenant', function () {
    $customer = Customer::factory()->create(['token' => 'echo-branding-token']);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-echo-branding.example.test',
    ]);

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $checksum = BrandingSyncPayload::computeChecksum($bytes);

    Queue::fake();

    $this->withToken('echo-branding-token')->postJson('/api/v1/sync/branding', [
        'app_url' => $subscription->url,
        'origin' => 'cms',
        'branding' => [
            'slot' => 'login_logo',
            'url' => 'https://cms-echo-branding.example.test/storage/login.png',
            'checksum' => $checksum,
            'cleared' => false,
            'updated_at' => now()->toIso8601String(),
        ],
    ])->assertSuccessful();

    Queue::assertNotPushed(PushBrandingToTenantsJob::class);
});
