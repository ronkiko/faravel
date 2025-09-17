<?php // v0.4.2
/* app/Providers/SecurityServiceProvider.php
Назначение: заглушка провайдера безопасности. В текущей версии VerifyCsrfToken
работает напрямую с $_SESSION и не требует отдельных сервисов.
FIX: переписан провайдер на безопасную заглушку без обращений к несуществующим
     классам (убраны Faravel\Security\Csrf\TokenManager и др.).
*/

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;

use App\Support\Logger;

final class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов безопасности (CSRF и пр.).
     *
     * В текущей архитектуре VerifyCsrfToken читает/пишет в $_SESSION, поэтому
     * отдельные биндинги не требуются. Оставляем провайдер для будущего расширения.
     *
     * @return void
     */
    public function register(): void
    {
        // Debug: provider register
        Logger::log('PROVIDER.REGISTER', static::class . ' register');
        // Ничего не регистрируем.
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
        // Пусто.
    }
}
