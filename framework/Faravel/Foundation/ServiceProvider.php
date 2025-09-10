<?php

namespace Faravel\Foundation;

use Faravel\Foundation\Application;

abstract class ServiceProvider
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * Create a new service provider instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Register services in the container.
     */
    public function register(): void
    {
        // Should be implemented by subclasses
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        // Optional override for subclasses
    }
}
