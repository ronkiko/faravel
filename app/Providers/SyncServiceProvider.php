<?php

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use App\Services\SyncManager;

/**
 * Регистрация службы синхронизации в контейнере Faravel.
 */
class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Регистрируем SyncManager как singleton
        $this->app->singleton(SyncManager::class, function () {
            return new SyncManager();
        });
    }

    public function boot(): void
    {
        // Пока ничего не делаем в boot
    }
}