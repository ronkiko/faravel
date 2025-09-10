<?php // v0.4.1
/* app/Providers/RoutingServiceProvider.php
Назначение: провайдер маршрутизации Faravel; регистрирует Router в DI-контейнере.
FIX: новый провайдер — добавлен singleton-биндинг для Faravel\Routing\Router.
*/

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use Faravel\Routing\Router;

final class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов слоя маршрутизации.
     *
     * В контексте ядра (Kernel) Router запрашивается через контейнер, поэтому
     * требуется явный биндинг. Класс Router использует статические реестры
     * маршрутов, но инстанс безопасен: вызовы dispatch() допустимы с инстанса.
     *
     * Предусловия: контейнер приложения инициализирован (bootstrap/app.php).
     * Побочные эффекты: изменяется состояние контейнера ($app->singleton()).
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Router::class, fn () => new Router());
        // По желанию можно добавить псевдоним-строку:
        // $this->app->singleton('router', fn () => $this->app->make(Router::class));
    }

    /**
     * Загрузка после регистрации. Ничего не делает, но оставлена для симметрии.
     *
     * @return void
     */
    public function boot(): void
    {
        // Ничего: маршруты грузятся через Application::loadRoutes().
    }
}
