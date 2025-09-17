<?php // v0.4.109
/* app/Providers/RequestServiceProvider.php
Purpose: Регистрирует Faravel\Http\Request в DI‑контейнере как singleton,
         чтобы Request можно было разрешить через `app(Faravel\Http\Request::class)`.
FIX: Новый провайдер — решение проблемы "Container binding [Faravel\Http\Request] not
     found." при создании Request в Kernel/Router. Версия файла v0.4.109.
*/

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use Faravel\Http\Request;
use App\Support\Logger;

/**
 * RequestServiceProvider
 *
 * Binds Faravel\Http\Request into the service container. This provider ensures
 * that the HTTP kernel and helpers can resolve the current request object via
 * `app(\Faravel\Http\Request::class)` or the request() helper. Without this
 * binding, the container would throw a RuntimeException when attempting to
 * construct the request.
 */
final class RequestServiceProvider extends ServiceProvider
{
    /**
     * Register the Request singleton in the container.
     *
     * @return void
     */
    public function register(): void
    {
        // Log provider registration
        Logger::log('PROVIDER.REGISTER', static::class . ' register');
        // Bind Request as a singleton if not already bound
        if (!isset($this->app[Request::class])) {
            $this->app->singleton(Request::class, static fn () => new Request());
        }
    }

    /**
     * Boot phase — optional here; no actions required. Logging for consistency.
     *
     * @return void
     */
    public function boot(): void
    {
        Logger::log('PROVIDER.BOOT', static::class . ' boot');
        // no-op
    }
}