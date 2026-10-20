<?php

use App\Services\Reporting\Aims\AimsReportingSettings;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('keeps a role grant when the display name is no longer the seed name', function () {
    $settings = new AimsReportingSettings;

    $pairs = $settings->pairs([
        [
            'grant_key' => 'investigator',
            'role_ids' => '[17,23]',
            'scope' => 'participant',
            'can_dashboard' => 0,
            'can_cases' => 1,
            'can_audit' => 0,
            'can_export' => 0,
            'can_see_names' => 1,
            'note' => 'was Investigator',
        ],
    ], 120);

    expect($pairs)->toContain(['key' => 'investigator_role_ids', 'value' => '17, 23'])
        ->and($pairs)->toContain(['key' => 'clock_offset_minutes', 'value' => '120']);

    $document = $settings->document($pairs);

    expect($document['grants'][0]['role_ids'])->toBe([17, 23])
        ->and($document['grants'][0]['scope'])->toBe('participant')
        ->and($document['grants'][0]['can_cases'])->toBe(1)
        ->and($document['grants'][0]['note'])->toBe('was Investigator')
        ->and($document['clock'])->toBe(120);
});

it('accepts a json array of role ids', function () {
    $document = (new AimsReportingSettings)->document([
        ['key' => 'investigator_role_ids', 'value' => '[17, 23]'],
        ['key' => 'investigator_scope', 'value' => 'participant'],
    ]);

    expect($document['grants'][0]['role_ids'])->toBe([17, 23])
        ->and($document['grants'][0]['can_cases'])->toBe(0)
        ->and($document['clock'])->toBeNull();
});

it('rejects a setting key that is not a role slot', function () {
    expect(fn () => (new AimsReportingSettings)->document([
        ['key' => 'not_a_setting', 'value' => '1'],
    ]))->toThrow(ValidationException::class);
});
