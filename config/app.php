<?php

return [
    'name'    => 'Faravel',
    'version' => '0.4.91',
    'debug'   => false,

    /*
     * Provider boot order matters:
     * - Core infra first (DB/Cache/Event)
     * - Session/Auth stack
     * - Gate provider (binds 'gate') and Security
     * - Ability provider (uses Gate::define)
     * - Localization
     * - Views
     * - Routing and HTTP middleware
     */
    'providers' => [

        // Core infra first
        App\Providers\DatabaseServiceProvider::class,
        App\Providers\CacheServiceProvider::class,
        App\Providers\EventServiceProvider::class,

        // Session/Auth stack
        App\Providers\SessionServiceProvider::class,
        App\Providers\AuthServiceProvider::class,

        // Gate and security
        App\Providers\GateServiceProvider::class,
        App\Providers\SecurityServiceProvider::class,

        // Abilities (uses Gate)
        App\Providers\AbilityServiceProvider::class,

        // Localization
        App\Providers\LangServiceProvider::class,

        // Views/UI
        App\Providers\ViewServiceProvider::class,
        App\Providers\ForumViewServiceProvider::class,

        // Routing + HTTP middleware wiring
        App\Providers\RoutingServiceProvider::class,
        App\Providers\HttpMiddlewareServiceProvider::class,

        // Optional legacy compat
        App\Providers\AuthContainerServiceProvider::class,

        // Compliance checks for ViewModels
        App\Providers\ViewModelComplianceServiceProvider::class,
    ],
];
