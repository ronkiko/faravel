<?php // v0.4.2
/* app/Providers/SessionServiceProvider.php
Назначение: предоставляет биндинг Faravel\Http\Session, чтобы его можно было
получать из контейнера при необходимости.
FIX: переписан провайдер — вместо несуществующих SessionManager/Store регистрируем
     Faravel\Http\Session. Устранены предупреждения анализатора.
*/

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use Faravel\Http\Session;

use App\Support\Logger;

final class SessionServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует сессионный сервис как singleton.
     *
     * В текущей реализации Session инкапсулирует нативный PHP-сеанс и механизм
     * flash-данных. Нам не нужен менеджер/драйверы — достаточно одного инстанса.
     *
     * @return void
     */
    public function register(): void
    {
        // Debug: provider register
        Logger::log('PROVIDER.REGISTER', static::class . ' register');

        if (!isset($this->app[Session::class])) {
            $this->app->singleton(Session::class, static fn () => new Session());
        }
    }

    /**
     * Boot-фаза не требуется.
     *
     * @return void
     */
    public function boot(): void
    {
        // Debug: provider boot
        Logger::log('PROVIDER.BOOT', static::class . ' boot');
        // Пусто: запуск сессии делает SessionMiddleware.
    }
}
