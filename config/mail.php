<?php

return [

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'resend' => [
            'transport' => 'resend',
            'key'       => env('RESEND_API_KEY'),
        ],

        'smtp' => [
            'transport'   => 'smtp',
            'url'         => env('MAIL_URL'),
            'host'        => env('MAIL_HOST', '127.0.0.1'),
            'port'        => env('MAIL_PORT', 2525),
            'encryption'  => env('MAIL_ENCRYPTION', 'tls'),
            'username'    => env('MAIL_USERNAME'),
            'password'    => env('MAIL_PASSWORD'),
            'timeout'     => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'log' => [
            'transport' => 'log',
            'channel'   => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers'   => ['resend', 'log'],
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'onboarding@resend.dev'),
        'name'    => env('MAIL_FROM_NAME', 'ADT Sports'),
    ],

];
