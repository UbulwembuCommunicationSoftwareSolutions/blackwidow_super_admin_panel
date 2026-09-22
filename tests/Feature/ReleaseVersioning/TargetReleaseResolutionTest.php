<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the type current release as the target by default', function () {
    $type = SubscriptionType::factory()->create();
    $current = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v1.0.0',
    ]);
    $type->forceFill(['current_release_id' => $current->id])->save();

    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'deployed_release_id' => null,
    ]);

    expect($sub->targetRelease()?->id)->toBe($current->id);
    expect($sub->isOutdated())->toBeTrue();
});

it('prefers a pinned release over the type current release', function () {
    $type = SubscriptionType::factory()->create();
    $current = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v2.0.0',
    ]);
    $pinned = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v1.9.0',
    ]);
    $type->forceFill(['current_release_id' => $current->id])->save();

    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'pinned_release_id' => $pinned->id,
        'deployed_release_id' => $pinned->id,
    ]);

    expect($sub->targetRelease()?->id)->toBe($pinned->id);
    expect($sub->isOutdated())->toBeFalse();
    expect($sub->releaseStatus())->toBe('pinned');
});

it('scopes whereOutdated to mismatched deployed releases', function () {
    $type = SubscriptionType::factory()->create();
    $current = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v5.0.0',
    ]);
    $old = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v4.0.0',
    ]);
    $type->forceFill(['current_release_id' => $current->id])->save();

    $outdated = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'deployed_release_id' => $old->id,
    ]);
    $currentSub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'deployed_release_id' => $current->id,
    ]);

    $ids = CustomerSubscription::query()->whereOutdated()->pluck('id');

    expect($ids)->toContain($outdated->id);
    expect($ids)->not->toContain($currentSub->id);
});
