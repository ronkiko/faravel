<?php

return [
    // Новый формат ключей (предпочтительный)
    'driver'   => env('DB_DRIVER', 'mysql'),
    'host'     => env('DB_HOST', 'mysql'),
    'port'     => (int)env('DB_PORT', 3306),

    // Современные имена с поддержкой старых .env (DB_NAME/DB_USER/DB_PASS)
    'database' => env('DB_DATABASE', env('DB_NAME', 'forum')),
    'username' => env('DB_USERNAME', env('DB_USER', 'user')),
    'password' => env('DB_PASSWORD', env('DB_PASS', 'u9uy8fDF%tr1@df')),

    // Совместимость: старые ключи все ещё читаются Database::connect() через fallback
    'name'     => env('DB_NAME', 'forum'),
    'user'     => env('DB_USER', 'user'),

    'charset'   => env('DB_CHARSET', 'utf8mb4'),
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),

    // Устойчивость при старте контейнеров
    'retries'        => (int)env('DB_RETRIES', 3),     // кол-во попыток
    'retry_sleep_ms' => (int)env('DB_RETRY_MS', 50),  // пауза между попытками
];
