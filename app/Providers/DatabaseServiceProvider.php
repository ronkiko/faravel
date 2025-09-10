<?php

namespace App\Providers;

use Faravel\Database\Database;
use Faravel\Foundation\ServiceProvider;

/**
 * Сервис‑провайдер для базы данных. Регистрирует экземпляр Database
 * в контейнере приложения.
 */
class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('db', function () {
            // Конфиг будет получен внутри Database автоматически
            return new Database();
        });
    }

    public function boot(): void
    {
        // Здесь можно выполнить операции после регистрации, если нужно
    }
}