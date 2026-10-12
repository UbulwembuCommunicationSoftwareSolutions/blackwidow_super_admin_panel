<?php

use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use App\Support\Sso\CookieDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function cookieDomainRow(CustomerSubscription $subscription): ?string
{
    return EnvVariables::query()
        ->where('customer_subscription_id', $subscription->id)
        ->where('key', 'COOKIE_DOMAIN')
        ->value('value');
}

/**
 * @return array<string, array<string, mixed>> Sites keyed by URL.
 */
function cookieDomainPlan(array $arguments): array
{
    Artisan::call('app:rollout-cookie-domain', array_merge(['--json' => true], $arguments));

    return collect(json_decode(Artisan::output(), true)['sites'])->keyBy('url')->all();
}

it('works out the shareable parent domain of a site', function (?string $url, ?string $expected) {
    expect(CookieDomain::forUrl($url))->toBe($expected);
})->with([
    ['https://cmsdemo.console.blackwidow.org.za', '.blackwidow.org.za'],
    ['demo.firearm.blackwidow.org.za/login', '.blackwidow.org.za'],
    ['https://consolehc.bvigilant.co.za', '.bvigilant.co.za'],
    ['https://ethekweniiu.console.aims.work', '.aims.work'],
    ['https://example.com', '.example.com'],
    ['https://super_admin_frontend-eumaqzrf.on-forge.com', null],
    ['https://102.219.40.123', null],
    ['https://blackwidow_cms.test', null],
    ['', null],
    [null, null],
]);

beforeEach(function () {
    config(['app.url' => 'https://superadmin-api.blackwidow.org.za']);
    $this->type = SubscriptionType::factory()->create();

    $make = fn (string $url): CustomerSubscription => CustomerSubscription::factory()->create([
        'subscription_type_id' => $this->type->id,
        'url' => $url,
    ]);

    $this->hub = $make('https://cmsdemo.console.blackwidow.org.za');
    $this->other = $make('https://consolehc.bvigilant.co.za');
    $this->shared = $make('https://tenant-abc.on-forge.com');
    $this->custom = $make('https://cpt.console.blackwidow.org.za');

    EnvVariables::query()->create([
        'customer_subscription_id' => $this->custom->id,
        'key' => 'COOKIE_DOMAIN',
        'value' => '.console.blackwidow.org.za',
    ]);
});

it('plans a value per site domain without writing anything', function () {
    $plan = cookieDomainPlan(['--type' => [$this->type->id]]);

    expect($plan[$this->hub->url])->toMatchArray(['proposed' => '.blackwidow.org.za', 'action' => 'add', 'hub_handoff' => true])
        ->and($plan[$this->other->url])->toMatchArray(['proposed' => '.bvigilant.co.za', 'action' => 'add', 'hub_handoff' => false])
        ->and($plan[$this->shared->url])->toMatchArray(['proposed' => null, 'action' => 'skip'])
        ->and($plan[$this->custom->url])->toMatchArray(['current' => '.console.blackwidow.org.za', 'action' => 'keep']);

    expect(cookieDomainRow($this->hub))->toBeNull()
        ->and(TemplateEnvVariables::query()->where('key', 'COOKIE_DOMAIN')->exists())->toBeFalse();
});

it('applies the plan to subscription rows and the type template, leaving existing values alone', function () {
    $this->artisan('app:rollout-cookie-domain', ['--type' => [$this->type->id], '--apply' => true, '--templates' => true])->assertSuccessful();

    expect(cookieDomainRow($this->hub))->toBe('.blackwidow.org.za')
        ->and(cookieDomainRow($this->other))->toBe('.bvigilant.co.za')
        ->and(cookieDomainRow($this->shared))->toBeNull()
        ->and(cookieDomainRow($this->custom))->toBe('.console.blackwidow.org.za')
        ->and(TemplateEnvVariables::query()
            ->where('subscription_type_id', $this->type->id)
            ->where('key', 'COOKIE_DOMAIN')
            ->value('value'))->toBe('.blackwidow.org.za');

    $plan = cookieDomainPlan(['--type' => [$this->type->id]]);

    expect($plan[$this->hub->url]['action'])->toBe('ok');
});

it('applies only to sites on the given domain and leaves the templates alone unless asked', function () {
    $this->artisan('app:rollout-cookie-domain', [
        '--type' => [$this->type->id],
        '--domain' => '.blackwidow.org.za',
        '--apply' => true,
    ])->assertSuccessful();

    expect(cookieDomainRow($this->hub))->toBe('.blackwidow.org.za')
        ->and(cookieDomainRow($this->other))->toBeNull()
        ->and(cookieDomainRow($this->custom))->toBe('.console.blackwidow.org.za')
        ->and(TemplateEnvVariables::query()->where('key', 'COOKIE_DOMAIN')->exists())->toBeFalse()
        ->and(array_keys(cookieDomainPlan(['--type' => [$this->type->id], '--domain' => '.bvigilant.co.za'])))
        ->toBe([$this->other->url]);
});
