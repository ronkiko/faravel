<?php

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use App\Services\SyncManager;

use App\Support\Logger;

/**
 * Регистрация службы синхронизации в контейнере Faravel.
 */
class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Debug: provider register
        Logger::log('PROVIDER.REGISTER', static::class . ' register');

        // Регистрируем SyncManager как singleton
        $this->app->singleton(SyncManager::class, function () {
            return new SyncManager();
        });
    }

    public function boot(): void
    {
        // Debug: provider boot
        Logger::log('PROVIDER.BOOT', static::class . ' boot');
        // Пока ничего не делаем в boot
    }
}