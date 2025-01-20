<?php

return [

    'driver' => env('MAIL_MAILER', 'smtp'),

    'transport' => env('MAIL_TRANSPORT', 'smtp'),

    'url' => env('MAIL_URL'),

    'host' => env('MAIL_HOST', 'smtp.mailgun.org'),

    'port' => env('MAIL_PORT', 587),

    'encryption' => env('MAIL_ENCRYPTION', 'tls'),

    'verify_peer' => false,

    'verify_peer_name' => false,

    'username' => env('MAIL_USERNAME'),

    'password' => env('MAIL_PASSWORD'),

    'timeout' => null,

    'local_domain' => env('MAIL_EHLO_DOMAIN'),

    'mailers' => [
        'mailgun' => [
            'transport' => 'mailgun',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],
    ],

];
