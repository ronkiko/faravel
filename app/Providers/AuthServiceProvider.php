<?php // v0.4.4
/* app/Providers/AuthServiceProvider.php
Purpose: Провайдер auth-подсистемы; регистрирует 'auth' и FQCN AuthService в DI.
FIX: добавлено логирование момента регистрации ('AUTH.PROVIDER.REGISTER'), чтобы
     видеть, когда провайдер реально исполняется относительно запросов к 'auth'.
*/

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use App\Services\Auth\AuthService;
use Faravel\Http\Session;
use App\Support\Debug;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Регистрация биндингов auth-сервиса.
     *
     * Предусловия: SessionServiceProvider биндит Faravel\Http\Session.
     * Побочные эффекты: добавляет singleton-инстансы в контейнер.
     *
     * @return void
     */
    public function register(): void
    {
        Debug::log('AUTH.PROVIDER.REGISTER', []);

        if (!isset($this->app[AuthService::class])) {
            $this->app->singleton(AuthService::class, function ($app) {
                /** @var Session $session */
                $session = $app->make(Session::class);
                return new AuthService($session);
            });
        }

        if (!isset($this->app['auth'])) {
            $this->app->singleton('auth', fn ($app) => $app->make(AuthService::class));
        }
    }

    /**
     * Boot-фаза сейчас не требуется.
     *
     * @return void
     */
    public function boot(): void
    {
        // Политики/гейты можно будет инициализировать здесь позже.
    }
}
