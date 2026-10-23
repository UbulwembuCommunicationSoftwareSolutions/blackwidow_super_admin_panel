<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\DeploymentTemplate;
use App\Models\SubscriptionType;
use App\Services\DeploymentScriptRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Creating a customer pushes it to the live LMS tenant: never let a test reach the network.
beforeEach(fn () => Http::fake());

function zdSubscription(array $subscription, ?string $native): CustomerSubscription
{
    $type = SubscriptionType::factory()->create(['project_type' => 'static', 'name' => 'Driver APP']);
    DeploymentTemplate::query()->create([
        'subscription_type_id' => $type->id,
        'script' => "cd /home/forge/#WEBSITE_URL#\ngit pull",
        'native_script' => $native,
    ]);

    return CustomerSubscription::factory()->create(array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'subscription_type_id' => $type->id,
        'domain' => 'zd.example.test',
    ], $subscription));
}

it('renders the native script for zero-downtime subscriptions', function () {
    $subscription = zdSubscription(['zero_downtime' => true], "\$CREATE_RELEASE()\nnpm run build\n\$ACTIVATE_RELEASE()");

    [$script] = app(DeploymentScriptRenderer::class)->render($subscription);

    expect($script->script)->toContain('$CREATE_RELEASE()')->not->toContain('git pull');
});

it('keeps rendering the classic script for classic subscriptions', function () {
    $subscription = zdSubscription(['zero_downtime' => false], "\$CREATE_RELEASE()\n\$ACTIVATE_RELEASE()");

    [$script] = app(DeploymentScriptRenderer::class)->render($subscription);

    expect($script->script)->toContain('git pull')->not->toContain('$CREATE_RELEASE()');
});

it('falls back to the classic script when the type has no native script', function () {
    $subscription = zdSubscription(['zero_downtime' => true], null);

    [$script] = app(DeploymentScriptRenderer::class)->render($subscription);

    expect($script->script)->toContain('git pull');
});

it('only treats templates with a native script as zero-downtime capable', function () {
    expect((new DeploymentTemplate(['native_script' => null]))->supportsZeroDowntime())->toBeFalse()
        ->and((new DeploymentTemplate(['native_script' => "\$CREATE_RELEASE()"]))->supportsZeroDowntime())->toBeTrue();
});

it('ships a native script with both macros for every bundled template', function () {
    $files = glob(resource_path('deployment-templates/native/*.sh'));

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $script = file_get_contents($file);
        expect($script)->toContain('$CREATE_RELEASE()')->toContain('$ACTIVATE_RELEASE()');
    }
});

it('loads native scripts into deployment templates by subscription type name', function () {
    $type = SubscriptionType::factory()->create(['project_type' => 'static', 'name' => 'Driver APP']);
    DeploymentTemplate::query()->create(['subscription_type_id' => $type->id, 'script' => 'git pull']);

    $this->artisan('app:load-native-deployment-templates')->assertSuccessful();

    $template = DeploymentTemplate::query()->where('subscription_type_id', $type->id)->first();
    expect($template->script)->toBe('git pull')
        ->and($template->native_script)->toContain('$CREATE_RELEASE()');
});
