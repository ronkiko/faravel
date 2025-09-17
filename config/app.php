<?php

return [
    'name'    => 'Faravel',
    // Current project version. Bumped on each patch.
    // Application version. Incremented with each patch.
    // Application version. Incremented with each patch.
    // Update: bumped version to 0.4.122. В этой версии: фиксы строгого VM вызова
    // в ShowTopicAction и регистрация прикладных абилок. См. CHANGELOG.
    'version' => '0.4.122',
    // Debug mode enabled: write log entries to storage/logs/debug.log for tracing requests.
    'debug'   => true,

    // ==== Auth options ====
    'auth' => [
        // TTL для кэширования записи пользователя в AuthService::user(), сек.
        // Короткий TTL исключает рассинхронизацию и уменьшает нагрузку на БД.
        'user_cache_ttl' => 60,
    ],

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

        // Core application bindings (Request, ResponseFactory, layout service, etc.)
        App\Providers\AppServiceProvider::class,

        // Routing + HTTP middleware wiring
        App\Providers\RoutingServiceProvider::class,
        App\Providers\HttpMiddlewareServiceProvider::class,

        // Optional legacy compat
        App\Providers\AuthContainerServiceProvider::class,

        // Compliance checks for ViewModels
        App\Providers\ViewModelComplianceServiceProvider::class,
    ],
];
