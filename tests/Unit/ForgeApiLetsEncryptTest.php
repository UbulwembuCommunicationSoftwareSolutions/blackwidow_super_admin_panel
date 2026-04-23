<?php

use App\Helpers\ForgeApi;

it('letsEncryptCertificate defaults to not waiting for install', function () {
    $method = new \ReflectionMethod(ForgeApi::class, 'letsEncryptCertificate');
    $params = $method->getParameters();
    expect($params)->toHaveCount(2);
    expect($params[1]->getName())->toBe('waitUntilInstalled');
    expect($params[1]->isDefaultValueAvailable() && $params[1]->getDefaultValue() === false)->toBeTrue();
});
