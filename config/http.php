<?php 

return [
    'middleware' => [
        // Executed for every request, in order.
        'global' => [
            \App\Http\Middleware\SessionMiddleware::class,
            \App\Http\Middleware\AuthContext::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\ThrottleRequests::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        ],

        // Named middlewares for routes.
        'aliases' => [
            'auth'    => \App\Http\Middleware\AuthMiddleware::class,
            'admin'   => \App\Http\Middleware\AdminOnly::class,
            'ability' => \App\Http\Middleware\AbilityMiddleware::class,
        ],
    ],
];
