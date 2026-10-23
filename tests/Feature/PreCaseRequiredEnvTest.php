<?php

use App\Helpers\ForgeApi;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use Database\Seeders\SubscriptionRequiredEnv\PreCaseRequiredEnvSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('seeds a Pre Case env that turns on the Super Admin user sync the module already runs', function () {
    SubscriptionType::factory()->create([
        'id' => 13,
        'name' => 'Pre Case',
        'project_type' => 'php',
    ]);

    $this->seed(PreCaseRequiredEnvSeeder::class);

    $template = TemplateEnvVariables::query()->where('subscription_type_id', 13)->pluck('value', 'key');

    expect($template->get('SUPERADMIN_SYNC_ENABLED'))->toBe('true')
        ->and($template->get('SUPERADMIN_API_URL'))->toBe('https://superadmin.blackwidow.org.za')
        ->and($template->get('SUPERADMIN_ACCESS_FLAG'))->toBe('precase_access')
        ->and($template->get('SUPERADMIN_SYSTEM_ADMIN_ROLE'))->toBe('Super Admin')
        ->and($template->get('SUPERADMIN_APP_URL'))->toBe('')
        ->and($template->get('SECURE_TOKEN'))->toBe('token')
        ->and($template->get('APP_ENV'))->toBe('production')
        ->and($template->get('SESSION_DRIVER'))->toBe('database')
        ->and($template->get('QUEUE_CONNECTION'))->toBe('database');
});

it('writes the subscription url and customer token into a new Pre Case env', function () {
    Queue::fake();
    config(['services.forge.key' => 'test-forge-key']);

    $type = SubscriptionType::factory()->create([
        'name' => 'Pre Case',
        'project_type' => 'php',
    ]);
    $this->seed(PreCaseRequiredEnvSeeder::class);

    $customer = Customer::factory()->create(['token' => 'customer-shared-secret']);
    $subscription = CustomerSubscription::factory()->create([
        'customer_id' => $customer->id,
        'subscription_type_id' => $type->id,
        'url' => 'https://demo.precase.blackwidow.org.za',
        'app_name' => 'Demo Pre Case',
        'database_name' => 'demo_precase',
    ]);

    (new ForgeApi)->addMissingEnv($subscription);

    $value = fn (string $key): ?string => EnvVariables::query()
        ->where('customer_subscription_id', $subscription->id)
        ->where('key', $key)
        ->value('value');

    expect($value('SUPERADMIN_SYNC_ENABLED'))->toBe('true')
        ->and($value('SUPERADMIN_ACCESS_FLAG'))->toBe('precase_access')
        ->and($value('SUPERADMIN_APP_URL'))->toBe('https://demo.precase.blackwidow.org.za')
        ->and($value('APP_URL'))->toBe('https://demo.precase.blackwidow.org.za')
        ->and($value('APP_NAME'))->toBe('Demo Pre Case')
        ->and($value('SECURE_TOKEN'))->toBe('customer-shared-secret')
        ->and($value('DB_DATABASE'))->toBe('demo_precase');
});
