<?php

namespace Faravel;

use Faravel\Foundation\Application;

class Services
{
    public static function register(Application $app): void
    {
        // Пример: регистрация логгера
        $app->singleton('logger', fn () => new \Faravel\Logger());

        // Пример: регистрация текущего времени (как демонстрация)
        $app->bind('time', fn () => date('H:i:s'));

        // ВАЖНО: 'view' теперь регистрируется через App\Providers\ViewServiceProvider
    }
}
