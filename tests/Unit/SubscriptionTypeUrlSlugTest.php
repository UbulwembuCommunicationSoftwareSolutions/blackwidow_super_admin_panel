<?php

use App\Models\SubscriptionType;

it('uses firearm as the host slug for type 2 even when named Firearm Module', function () {
    $type = new SubscriptionType(['name' => 'Firearm Module']);
    $type->id = 2;

    expect($type->url_slug)->toBe('firearm')
        ->and(SubscriptionType::urlSlugFor(2, 'Firearm Module'))->toBe('firearm');
});

it('rewrites a firearm-module host onto the canonical firearm label', function () {
    expect(SubscriptionType::canonicalizeHost(
        'https://demo.firearm-module.blackwidow.org.za',
        2,
        'Firearm Module',
    ))->toBe('https://demo.firearm.blackwidow.org.za')
        ->and(SubscriptionType::canonicalizeHost(
            'demo.firearm-module.blackwidow.org.za',
            2,
        ))->toBe('demo.firearm.blackwidow.org.za')
        ->and(SubscriptionType::canonicalizeHost(
            'demo.firearm.blackwidow.org.za',
            2,
        ))->toBe('demo.firearm.blackwidow.org.za');
});

it('does not rewrite hosts for other subscription types', function () {
    expect(SubscriptionType::canonicalizeHost(
        'demo.console.blackwidow.org.za',
        1,
        'Console',
    ))->toBe('demo.console.blackwidow.org.za');
});
