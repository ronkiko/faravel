<?php

namespace App\Providers;

use Faravel\Events\Dispatcher;
use Faravel\Foundation\ServiceProvider;

use App\Support\Logger;

/**
 * Сервис‑провайдер для событий. Регистрирует диспетчер событий
 * и позволяет добавлять слушателей в boot().
 */
class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Debug: provider register
        Logger::log('PROVIDER.REGISTER', static::class . ' register');

        $this->app->singleton('events', function () {
            return new Dispatcher();
        });
        // Регистрируем через класс, чтобы resolve(Dispatcher::class) работал
        $this->app->singleton(Dispatcher::class, function () {
            return new Dispatcher();
        });
    }

    public function boot(): void
    {
        // Debug: provider boot
        Logger::log('PROVIDER.BOOT', static::class . ' boot');

        // Здесь можно регистрировать слушателей для событий, например:
        // Event::listen('user.registered', function ($user) {
        //     // отправить приветственное письмо
        // });
    }
}