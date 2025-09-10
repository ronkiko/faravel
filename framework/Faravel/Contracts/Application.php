<?php

namespace Faravel\Contracts;

use Faravel\Foundation\ServiceProvider;

interface Application
{
    /**
     * Register a service provider.
     */
    public function register(string|ServiceProvider $provider): ServiceProvider;

    /**
     * Boot all registered service providers.
     */
    public function boot(): void;

    /**
     * Get the base path of the application.
     */
    public function basePath(): string;
}
