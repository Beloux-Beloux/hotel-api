<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WebSocket Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Node.js WebSocket server
    |
    */

    'url' => env('WEBSOCKET_URL', 'http://localhost:3001'),
    
    'api_key' => env('WEBSOCKET_API_KEY', ''),
    
    'jwt_secret' => env('JWT_SECRET', ''),
];