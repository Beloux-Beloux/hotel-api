<?php

return [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY')?? 'votre-cle-api-websocket-2024',
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'host' => env('PUSHER_HOST', '127.0.0.1'),
            'port' => env('PUSHER_PORT', 3001),
            'scheme' => env('PUSHER_SCHEME', 'http'),
            'encrypted' => false,  // IMPORTANT : false pour HTTP en local
            'useTLS' => env('PUSHER_SCHEME') === 'https',
        ],
    ],
];
