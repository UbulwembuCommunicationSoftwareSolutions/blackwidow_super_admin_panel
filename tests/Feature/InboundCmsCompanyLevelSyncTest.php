<?php

use App\Jobs\SiteDeployment\SendSystemConfigJob;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function companyLevelSyncPayload(array $overrides = []): array
{
    return array_merge([
        'app_url' => 'https://cms-levels.example.test',
        'origin' => 'cms',
        'level_one_in_use' => true,
        'level_one_description' => 'Region',
        'level_two_in_use' => true,
        'level_two_description' => 'Directorate',
        'level_three_in_use' => true,
        'level_three_description' => 'Department',
        'level_four_in_use' => true,
        'level_four_description' => 'Unit',
        'level_five_description' => 'Station',
        'task_description' => 'Job',
        'docket_description' => 'Case',
    ], $overrides);
}

it('stores company levels from cms without pushing them back', function () {
    Queue::fake();

    $customer = Customer::factory()->create([
        'token' => 'levels-token',
        'level_one_in_use' => false,
        'level_one_description' => 'Level 1',
        'level_four_in_use' => null,
    ]);
    SubscriptionType::factory()->create(['id' => 1, 'name' => 'CMS']);
    CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => 1,
        'url' => 'https://cms-levels.example.test',
    ]);

    $this->withToken('levels-token')
        ->postJson('/api/v1/sync/company-levels', companyLevelSyncPayload())
        ->assertSuccessful();

    $fresh = $customer->fresh();

    expect($fresh->level_one_description)->toBe('Region')
        ->and((bool) $fresh->level_one_in_use)->toBeTrue()
        ->and((bool) $fresh->getAttributes()['level_four_in_use'])->toBeTrue()
        ->and($fresh->level_four_description)->toBe('Unit')
        ->and($fresh->docket_description)->toBe('Case')
        ->and($fresh->task_description)->toBe('Job');

    Queue::assertNotPushed(SendSystemConfigJob::class);
});

it('pushes system config when level four changes on the customer', function () {
    Queue::fake();

    $customer = Customer::factory()->create([
        'level_four_in_use' => null,
    ]);

    $customer->level_four_in_use = true;
    $customer->save();

    Queue::assertPushed(SendSystemConfigJob::class, function (SendSystemConfigJob $job) use ($customer) {
        return $job->customerId === $customer->id;
    });
});
