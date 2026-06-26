<?php
// config/cache.php
return [
    'default' => env('CACHE_DRIVER', 'file'),
    'stores'  => [
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data'), 'lock_path' => storage_path('framework/cache/data')],
        'database' => [
            'driver'          => 'database',
            'table'           => 'cache',
            'connection'      => null,
            'lock_connection' => null,
            'lock_table'      => 'cache_locks',
        ],
        'redis' => [
            'driver'          => 'redis',
            'connection'      => 'cache',
            'lock_connection' => 'default',
        ],
        'array' => ['driver' => 'array', 'serialize' => false],
        'null'  => ['driver' => 'null'],
    ],
    'prefix' => env('CACHE_PREFIX', 'adt_sports_cache'),
];
