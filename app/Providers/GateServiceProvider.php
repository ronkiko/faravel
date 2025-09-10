<?php // v0.4.2
/* app/Providers/GateServiceProvider.php
Purpose: Register the authorization layer in the container: bind core Auth and the GateManager
         under the 'gate' key so facades and policies can work during the new Application
         lifecycle (thin controllers era).
FIX: Switched to Faravel\Auth\Auth (removed CoreAuth alias). Removed any dependency on
     $app->bound(). Always registers singletons for Auth, 'gate', and GateManager FQCN.
*/

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use Faravel\Auth\Auth;
use Faravel\Auth\GateManager;

/**
 * Провайдер авторизации (Gate). Чистая инфраструктура DI, без бизнес-логики.
 * Регистрирует:
 *  - singleton(Auth::class)
 *  - singleton('gate') → GateManager (для фасада)
 *  - singleton(GateManager::class) → удобство для DI по FQCN
 */
final class GateServiceProvider extends ServiceProvider
{
    /**
     * Register core Auth and GateManager in the container.
     *
     * Предусловия: автозагрузка ядра доступна.
     * Побочные эффекты: добавляет 3 singleton-сервиса в контейнер.
     *
     * @return void
     */
    public function register(): void
    {
        // 1) Core Auth singleton (framework service).
        $this->app->singleton(Auth::class, static function (): Auth {
            // Auth has parameterless constructor; it will access session internally as needed.
            return new Auth();
        });

        // 2) Bind GateManager under string key 'gate' (used by the Gate facade).
        $this->app->singleton('gate', function ($app): GateManager {
            /** @var Auth $auth */
            $auth = $app->make(Auth::class);
            return new GateManager($auth);
        });

        // 3) Also bind by FQCN for constructor DI convenience.
        $this->app->singleton(GateManager::class, function ($app): GateManager {
            /** @var Auth $auth */
            $auth = $app->make(Auth::class);
            return new GateManager($auth);
        });
    }

    /**
     * Bootstrap phase is not required here — abilities are wired elsewhere.
     *
     * @return void
     */
    public function boot(): void
    {
        // Intentionally empty.
    }
}
