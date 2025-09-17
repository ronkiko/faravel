<?php // v0.4.5
/* app/Providers/AuthServiceProvider.php
Purpose: Провайдер auth-подсистемы; регистрирует 'auth' и AuthService в DI.
FIX: Логирование перестроено. Используется новый Logger для записи простой
     строки «Loaded OK» с тегом [AUTH.PROVIDER.REGISTER] в storage/logs/debug.log
     при включённом отладочном режиме (app.debug = true).
*/

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use App\Services\Auth\AuthService;
use Faravel\Http\Session;
use App\Support\Logger;

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
        // Write a human‑readable debug message when registering the provider
        Logger::log('AUTH.PROVIDER.REGISTER', 'Loaded OK');

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
