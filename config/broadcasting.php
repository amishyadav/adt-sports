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

        /*
         * Pusher's hosted service. The right choice on hosting without shell
         * access: the app only makes outbound HTTPS calls and browsers connect
         * to Pusher directly, so nothing long-running has to be kept alive.
         * Set BROADCAST_CONNECTION=pusher and fill in the four PUSHER_* keys.
         */
        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'ap2'),
                'useTLS'  => true,
            ],
            'client_options' => [
                // The scoreboard must never hang waiting on the broadcast API;
                // MatchScreenController swallows failures either way.
                'timeout'         => 4,
                'connect_timeout' => 3,
            ],
        ],

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
