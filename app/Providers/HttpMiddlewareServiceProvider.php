<?php // v0.4.5
/* app/Providers/HttpMiddlewareServiceProvider.php
Purpose: Bind HTTP middleware classes to the container and publish maps for the router.
         Reads middleware lists from config/http.php and publishes them — single source of truth.
FIX: Removed defaults/fallbacks. Provider now validates config strictly and throws descriptive
     errors if keys are missing/invalid. Publishes arrays and binds each class as singleton.
*/

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use Faravel\Support\Config;

use App\Support\Logger;

/**
 * HTTP middleware wiring provider.
 *
 * This provider is the single source of truth for:
 *  - binding concrete middleware classes (singleton),
 *  - publishing the global list and alias map into the container for the router/kernel.
 */
final class HttpMiddlewareServiceProvider extends ServiceProvider
{
    /**
     * Register middleware services in the container and expose alias maps.
     *
     * Preconditions:
     *  - config/http.php must define:
     *      http.middleware.global  => array<int, class-string>
     *      http.middleware.aliases => array<string, class-string>
     *  - Middleware classes have no required constructor args (DI-less).
     *
     * Side effects:
     *  - Binds each middleware class as a singleton.
     *  - Publishes 'http.middleware.global' and 'http.middleware.aliases' arrays into container.
     *
     * @return void
     *
     * @throws \RuntimeException When config keys are missing/invalid or global list is empty.
     * @example
     *  // config/http.php
     *  return [
     *    'middleware' => [
     *      'global' => [\App\Http\Middleware\SessionMiddleware::class, ...],
     *      'aliases' => ['auth' => \App\Http\Middleware\AuthMiddleware::class, ...],
     *    ],
     *  ];
     */
    public function register(): void
    {
        // Debug: provider register
        Logger::log('PROVIDER.REGISTER', static::class . ' register');

        $global = Config::get('http.middleware.global', null);
        $aliases = Config::get('http.middleware.aliases', null);

        if (!is_array($global)) {
            throw new \RuntimeException(
                "Config 'http.middleware.global' is missing or invalid. ".
                "Define it in config/http.php under ['middleware']['global']."
            );
        }
        if (!is_array($aliases)) {
            throw new \RuntimeException(
                "Config 'http.middleware.aliases' is missing or invalid. ".
                "Define it in config/http.php under ['middleware']['aliases']."
            );
        }

        // Validate alias map shape: string => class-string
        $aliasMap = [];
        foreach ($aliases as $k => $v) {
            if (!is_string($k) || !is_string($v)) {
                throw new \RuntimeException(
                    "Config 'http.middleware.aliases' must be a map<string, class-string>."
                );
            }
            $aliasMap[$k] = $v;
        }

        // Sanitize global list
        $globalList = [];
        foreach ($global as $v) {
            if (is_string($v)) {
                $globalList[] = $v;
            }
        }
        $globalList = array_values(array_unique($globalList));

        if ($globalList === []) {
            throw new \RuntimeException(
                "Config 'http.middleware.global' must contain at least one middleware class."
            );
        }

        // 1) Bind concrete middleware classes as singletons.
        foreach (array_unique(array_merge($globalList, array_values($aliasMap))) as $mwClass) {
            $this->app->singleton($mwClass, static fn () => new $mwClass());
        }

        // 2) Publish maps for the router/kernel.
        $this->app->singleton('http.middleware.global', static fn (): array => $globalList);
        $this->app->singleton('http.middleware.aliases', static fn (): array => $aliasMap);
    }

    /**
     * Boot is not required — router/kernel read maps from the container on demand.
     *
     * @return void
     */
    public function boot(): void
    {
        // Debug: provider boot
        Logger::log('PROVIDER.BOOT', static::class . ' boot');
        // no-op
    }
}
