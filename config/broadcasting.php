<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | Set BROADCAST_CONNECTION=reverb to push live match state over WebSockets.
    | Leaving it on "null" disables pushing entirely — the screens then fall
    | back to their (slower) HTTP reconcile poll, so scoring still works.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key'    => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host'   => env('REVERB_HOST', '127.0.0.1'),
                'port'   => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [
                // Broadcasts are sent inline (ShouldBroadcastNow) so a point
                // reaches the screens without waiting on a queue worker. Keep
                // the timeout short: the scoreboard must never hang on a dead
                // WebSocket server, and MatchScreenController swallows failures.
                'timeout'         => 3,
                'connect_timeout' => 2,
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
