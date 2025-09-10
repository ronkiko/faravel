<?php // v0.4.4
/* app/Providers/AppServiceProvider.php
Purpose: Главный провайдер приложения. Регистрирует DI-биндинги «по-ларaвеловски»
и выполняет лёгкую инициализацию уровня приложения.
FIX: Удалены debug-биндинги/маршруты и диагностические записи; чистая регистрация
     сервисов. Старт сессии перенесён в SessionMiddleware.
*/
namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use Faravel\Http\ResponseFactory;
use App\Services\Passat;
use Faravel\Http\Session;
use Faravel\Http\Request;
use App\Services\Layout\LayoutService;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов и биндингов в контейнере.
     *
     * @return void
     */
    public function register(): void
    {
        // Example app service
        $this->app->bind(Passat::class, function () {
            return new Passat();
        });

        // Response factory singleton
        $this->app->singleton(ResponseFactory::class, function () {
            return new ResponseFactory();
        });

        // Global exception handler
        $this->app->singleton(\App\Exceptions\Handler::class, function () {
            return new \App\Exceptions\Handler(new \Faravel\Logger());
        });

        // Session as per-request singleton (created by container each request; start() in middleware)
        $this->app->singleton(Session::class, function () {
            return new Session();
        });

        // Request as per-request singleton (refreshed by middleware)
        $this->app->singleton(Request::class, function () {
            return new Request();
        });

        // Stateless layout builder
        $this->app->singleton(LayoutService::class, function () {
            return new LayoutService();
        });
    }

    /**
     * Bootstrap hooks (none for now).
     *
     * @return void
     */
    public function boot(): void
    {
        // no-op
    }
}
