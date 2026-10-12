<?php

use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * @param  array<string, string|null>  $rows
 */
function envReportSubscription(SubscriptionType $type, array $rows, ?string $siteEnv = null): CustomerSubscription
{
    $subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => $type->id,
        'env' => $siteEnv,
    ]);

    foreach ($rows as $key => $value) {
        EnvVariables::query()->create([
            'customer_subscription_id' => $subscription->id,
            'key' => $key,
            'value' => $value,
        ]);
    }

    return $subscription;
}

/**
 * @return array{checked: int, unknown_keys: list<string>, issues: list<array{subscription_id: int, key: string, problems: list<string>}>}
 */
function envReport(array $arguments = []): array
{
    Artisan::call('app:report-missing-env', array_merge(['--json' => true], $arguments));

    return json_decode(Artisan::output(), true);
}

beforeEach(function () {
    $this->type = SubscriptionType::factory()->create();

    TemplateEnvVariables::factory()->create([
        'subscription_type_id' => $this->type->id,
        'key' => 'COOKIE_DOMAIN',
        'value' => '.blackwidow.org.za',
    ]);
    TemplateEnvVariables::factory()->create([
        'subscription_type_id' => $this->type->id,
        'key' => 'SURVEY_APP_NAME',
        'value' => '',
    ]);
});

it('passes when every template key is configured and on the site', function () {
    envReportSubscription(
        $this->type,
        ['COOKIE_DOMAIN' => '.blackwidow.org.za', 'SURVEY_APP_NAME' => ''],
        "COOKIE_DOMAIN=.blackwidow.org.za\nSURVEY_APP_NAME=\n",
    );

    $this->artisan('app:report-missing-env')->assertSuccessful();

    expect(envReport())->toMatchArray(['checked' => 1, 'unknown_keys' => [], 'issues' => []]);
});

it('reports a key with no row, an empty value, or missing from the pulled site env', function () {
    $noRow = envReportSubscription($this->type, ['SURVEY_APP_NAME' => '']);
    $empty = envReportSubscription($this->type, ['COOKIE_DOMAIN' => null, 'SURVEY_APP_NAME' => '']);
    $notOnSite = envReportSubscription(
        $this->type,
        ['COOKIE_DOMAIN' => '.blackwidow.org.za', 'SURVEY_APP_NAME' => ''],
        "APP_ENV=production\nSURVEY_APP_NAME=\n",
    );

    $this->artisan('app:report-missing-env')->assertFailed();

    $problems = collect(envReport()['issues'])->mapWithKeys(
        fn (array $issue) => [$issue['subscription_id'].':'.$issue['key'] => $issue['problems']]
    );

    expect($problems->all())->toEqualCanonicalizing([
        $noRow->id.':COOKIE_DOMAIN' => ['no_row'],
        $empty->id.':COOKIE_DOMAIN' => ['empty'],
        $notOnSite->id.':COOKIE_DOMAIN' => ['not_on_site'],
    ]);
});

it('limits the check to the given keys and subscription types', function () {
    $otherType = SubscriptionType::factory()->create();
    TemplateEnvVariables::factory()->create([
        'subscription_type_id' => $otherType->id,
        'key' => 'COOKIE_DOMAIN',
        'value' => '.blackwidow.org.za',
    ]);
    TemplateEnvVariables::factory()->create([
        'subscription_type_id' => $otherType->id,
        'key' => 'APP_NAME',
        'value' => 'Other',
    ]);

    envReportSubscription($this->type, []);
    $other = envReportSubscription($otherType, []);

    $report = envReport(['keys' => ['COOKIE_DOMAIN'], '--type' => [$otherType->id]]);

    expect($report['checked'])->toBe(1)
        ->and($report['issues'])->toHaveCount(1)
        ->and($report['issues'][0])->toMatchArray([
            'subscription_id' => $other->id,
            'key' => 'COOKIE_DOMAIN',
            'problems' => ['no_row'],
        ]);
});

it('changes nothing', function () {
    $subscription = envReportSubscription($this->type, []);

    envReport();

    expect(EnvVariables::query()->where('customer_subscription_id', $subscription->id)->count())->toBe(0);
});

it('fails and names a requested key that no template holds', function () {
    envReportSubscription($this->type, ['COOKIE_DOMAIN' => '.blackwidow.org.za', 'SURVEY_APP_NAME' => '']);

    $this->artisan('app:report-missing-env', ['keys' => ['SSO_HANDOFF_URL']])
        ->expectsOutputToContain('SSO_HANDOFF_URL')
        ->assertFailed();

    expect(envReport(['keys' => ['SSO_HANDOFF_URL', 'COOKIE_DOMAIN']]))->toMatchArray([
        'checked' => 1,
        'unknown_keys' => ['SSO_HANDOFF_URL'],
        'issues' => [],
    ]);
});

it('checks a key outside the template on every subscription when told to ignore the template', function () {
    $untemplated = SubscriptionType::factory()->create();
    $has = envReportSubscription($untemplated, ['SSO_HANDOFF_URL' => 'https://sa.example.test/go']);
    $blank = envReportSubscription($untemplated, ['SSO_HANDOFF_URL' => '']);
    $lacks = envReportSubscription($this->type, []);

    $report = envReport(['keys' => ['SSO_HANDOFF_URL'], '--ignore-template' => true]);

    $problems = collect($report['issues'])->mapWithKeys(
        fn (array $issue) => [$issue['subscription_id'] => $issue['problems']]
    );

    expect($report['checked'])->toBe(3)
        ->and($report['unknown_keys'])->toBe([])
        ->and($problems->has($has->id))->toBeFalse()
        ->and($problems[$blank->id])->toBe(['empty'])
        ->and($problems[$lacks->id])->toBe(['no_row']);

    $this->artisan('app:report-missing-env', ['--ignore-template' => true])->assertExitCode(2);
});
