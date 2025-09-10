<?php

return [
    'name'    => 'Faravel',
    'version' => '0.3.8',
    'debug'   => false,

    /*
     * Provider boot order matters:
     * - Core infra first (DB/Cache/Event)
     * - Session/Auth stack
     * - Gate provider (binds 'gate')
     * - Ability provider (uses Gate::define)
     * - Views
     * - Routing and HTTP middleware
     */
    'providers' => [
        // Core & infra
        App\Providers\AppServiceProvider::class,
        App\Providers\DatabaseServiceProvider::class,
        App\Providers\CacheServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\SyncServiceProvider::class,
        App\Providers\LangServiceProvider::class,

        // HTTP session first (auth depends on it)
        App\Providers\SessionServiceProvider::class,

        // Auth containers
        App\Providers\AuthServiceProvider::class,

        // Gate must appear before abilities
        App\Providers\GateServiceProvider::class,
        App\Providers\SecurityServiceProvider::class,

        // Abilities (now safe to use Gate)
        App\Providers\AbilityServiceProvider::class,

        // Views/UI
        App\Providers\ViewServiceProvider::class,
        App\Providers\ForumViewServiceProvider::class,

        // Routing + HTTP middleware wiring
        App\Providers\RoutingServiceProvider::class,
        App\Providers\HttpMiddlewareServiceProvider::class,

        // Optional legacy compat
        App\Providers\AuthContainerServiceProvider::class,
        
        App\Providers\ViewModelComplianceServiceProvider::class,
    ],
];
