<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use App\Services\DeploymentScriptRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders website url and release tag placeholders from the template', function () {
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v2.0.0',
    ]);
    $type->forceFill(['current_release_id' => $release->id])->save();

    DeploymentTemplate::query()->create([
        'subscription_type_id' => $type->id,
        'script' => "cd /home/forge/#WEBSITE_URL#\ngit checkout --force #RELEASE_TAG#\necho \"BW_DEPLOYED_RELEASE #RELEASE_TAG#\"\n",
    ]);

    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'domain' => 'demo.example.test',
        'server_id' => null,
        'forge_site_id' => null,
    ]);

    [$script] = app(DeploymentScriptRenderer::class)->render($sub);

    expect($script->script)
        ->toContain('cd /home/forge/demo.example.test')
        ->toContain('git checkout --force v2.0.0')
        ->not->toContain('#RELEASE_TAG#')
        ->not->toContain('#WEBSITE_URL#');
    expect($script->rendered_release_id)->toBe($release->id);
    expect($script->is_custom)->toBeFalse();
});

it('does not overwrite custom scripts from the template but still substitutes placeholders', function () {
    $type = SubscriptionType::factory()->create();
    $release = SubscriptionTypeRelease::factory()->create([
        'subscription_type_id' => $type->id,
        'tag' => 'v3.1.0',
    ]);
    $type->forceFill(['current_release_id' => $release->id])->save();

    DeploymentTemplate::query()->create([
        'subscription_type_id' => $type->id,
        'script' => "FROM_TEMPLATE #RELEASE_TAG#\n",
    ]);

    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
        'domain' => 'custom.example.test',
    ]);

    DeploymentScript::query()->create([
        'customer_subscription_id' => $sub->id,
        'script' => "CUSTOM #WEBSITE_URL# #RELEASE_TAG#\n",
        'is_custom' => true,
    ]);

    [$script] = app(DeploymentScriptRenderer::class)->render($sub->fresh());

    expect($script->script)->toBe("CUSTOM custom.example.test v3.1.0\n");
    expect($script->is_custom)->toBeTrue();
});

it('throws when the template requires a release tag but none is targeted', function () {
    $type = SubscriptionType::factory()->create(['current_release_id' => null]);
    DeploymentTemplate::query()->create([
        'subscription_type_id' => $type->id,
        'script' => "git checkout --force #RELEASE_TAG#\n",
    ]);
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'customer_id' => Customer::factory(),
    ]);

    app(DeploymentScriptRenderer::class)->render($sub);
})->throws(RuntimeException::class, 'No target release');
