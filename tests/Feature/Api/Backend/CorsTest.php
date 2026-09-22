<?php

$superadminOrigins = [
    'aims.world' => 'https://superadmin.aims.world',
    'bvigilant' => 'https://superadmin.bvigilant.co.za',
    'blackwidow' => 'https://superadmin.blackwidow.org.za',
    'siyaleader' => 'https://superadmin.siyaleader.org.za',
    'aims.net.za' => 'https://superadmin.aims.net.za',
];

it('allows cors preflight from branded superadmin origins', function (string $origin) {
    $this->withHeaders([
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'content-type,authorization,accept',
    ])->options('/api/backend/login')
        ->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeader('Access-Control-Allow-Headers');
})->with($superadminOrigins);

it('echoes allow-origin on api responses from branded superadmin origins', function (string $origin) {
    $this->withHeaders(['Origin' => $origin])
        ->getJson('/api/backend/user')
        ->assertUnauthorized()
        ->assertHeader('Access-Control-Allow-Origin', $origin);
})->with($superadminOrigins);

it('does not allow cors from an unknown origin', function () {
    $this->withHeaders([
        'Origin' => 'https://evil.example.com',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'content-type,authorization',
    ])->options('/api/backend/login')
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});
