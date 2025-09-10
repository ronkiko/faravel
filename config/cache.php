<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache connection that gets used while
    | using this caching library. This driver manages how and where cached
    | items are stored. You may set a default environment variable in your
    | .env file (CACHE_DRIVER) to override this value without editing the code.
    |
    */
    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    */
    'stores' => [
        'file' => [
            'driver' => 'file',
            // The path where file based caches should be stored. If null, the
            // Cache class will use the default path in storage/cache.
            'path' => base_path('storage/cache'),
        ],
        'memcached' => [
            'driver' => 'memcached',
            // Memcached servers configuration. You may set multiple servers
            // with individual host, port and weight values.
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],
    ],
];