<?php

return [
    'defaults' => [
        'guard' => 'api',
        'passwords' => 'users',
    ],

    'guards' => [
        'api' => [
            'driver' => 'api',
            'provider' => 'users',
            'hash' => false,
        ],
        'manager' => [
            'driver' => 'api',
            'provider' => 'users',
        ],
        'admin' => [
            'driver' => 'api',
            'provider' => 'users',
        ],
        'staff' => [
            'driver' => 'api',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => \App\Models\User::class,
        ],
    ],
];