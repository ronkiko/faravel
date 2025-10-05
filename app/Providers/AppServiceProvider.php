<?php // v0.4.6
/* app/Providers/AppServiceProvider.php
Purpose: Главный провайдер приложения. Регистрирует DI-биндинги «по-ларaвеловски»
и выполняет лёгкую инициализацию уровня приложения.
FIX: Удалён необязательный биндинг TopicCreateService — сервис создаётся авто-вайром
через рефлексию Router'а. Провайдер содержит только реально нужные биндинги.
*/
namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use Faravel\Http\ResponseFactory;
use App\Services\Passat;
use Faravel\Http\Session;
use Faravel\Http\Request;
use App\Services\Layout\LayoutService;
use App\Support\Logger;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов и биндингов в контейнере.
     *
     * @return void
     */
    public function register(): void
    {
        Logger::log('PROVIDER.REGISTER', static::class . ' register');

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

        // Session as per-request singleton
        $this->app->singleton(Session::class, function () {
            return new Session();
        });

        // Request as per-request singleton
        $this->app->singleton(Request::class, function () {
            return new Request();
        });

        // Stateless layout builder
        $this->app->singleton(LayoutService::class, function () {
            return new LayoutService();
        });

        // NOTE: TopicCreateService биндинг не требуется: конструктор пустой,
        // Router::buildViaReflection() корректно создаёт его по месту использования.
    }

    /**
     * Bootstrap hooks (none for now).
     *
     * @return void
     */
    public function boot(): void
    {
        Logger::log('PROVIDER.BOOT', static::class . ' boot');
        // no-op
    }
}
