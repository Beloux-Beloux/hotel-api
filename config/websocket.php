<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WebSocket Server URL
    |--------------------------------------------------------------------------
    |
    | The URL where your WebSocket server is running
    |
    */
    'url' => env('WEBSOCKET_URL', 'http://localhost:3001'),

    /*
    |--------------------------------------------------------------------------
    | WebSocket API Key
    |--------------------------------------------------------------------------
    |
    | API key for authenticating broadcast requests to the WebSocket server
    |
    */
    'api_key' => env('WEBSOCKET_API_KEY', 'votre-cle-api-websocket-2024'),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Client URL
    |--------------------------------------------------------------------------
    |
    | The WebSocket URL that clients should connect to
    |
    */
    'client_url' => env('WEBSOCKET_CLIENT_URL', 'ws://localhost:3001'),
];