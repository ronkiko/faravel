<?php // v0.4.4
/* app/Providers/ViewServiceProvider.php
Purpose: Register FileViewFinder, Php/Blade engines, View factory and safe Blade directives.
FIX: В boot добавлены безопасные директивы @csrf и @method, а также View::share('_csrf').
Директивы возвращают только HTML, без PHP. Совместимо со строгим Blade.
*/
namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use Faravel\View\Engines\PhpEngine;
use Faravel\View\Engines\BladeEngine;
use Faravel\View\FileViewFinder;
use Faravel\View\ViewFactory;
use Faravel\Support\Facades\Blade;

use App\Support\Logger;

final class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register bindings for view finder, engines and factory.
     */
    public function register(): void
    {
        Logger::log('PROVIDER.REGISTER', static::class . ' register');

        // View finder (single root path). Read from config('view.paths'), fallback to /resources/views
        $this->app->singleton('view.finder', function ($app) {
            $paths = $app['config']['view.paths'] ?? null;
            $viewPath = is_array($paths) ? (string)($paths[0] ?? '') : (string)($paths ?? '');
            if ($viewPath === '' || !is_dir($viewPath)) {
                $viewPath = rtrim($app->basePath(), '/') . '/resources/views';
            }
            return new FileViewFinder([$viewPath], ['blade.php', 'php']);
        });

        // Plain PHP engine
        $this->app->singleton('view.engine.php', function () {
            return new PhpEngine();
        });

        // View factory + engines wired на один и тот же экземпляр BladeEngine
        $this->app->singleton('view', function ($app) {
            /** @var FileViewFinder $finder */
            $finder  = $app->make('view.finder');

            $factory = new ViewFactory($finder);

            // Один и тот же экземпляр движка и в фабрике, и в фасаде Blade:
            $php   = $app->make('view.engine.php');
            $blade = new BladeEngine($factory);

            $factory->addEngine('php', $php);
            $factory->addEngine('blade.php', $blade);

            // Привяжем этот же экземпляр под ключ 'blade' для фасада Blade
            if (method_exists($app, 'instance')) {
                $app->instance('blade', $blade);
            }

            return $factory;
        });

        // Важно: отдельный singleton 'view.engine.blade' не регистрируем, чтобы не дублировать движок.
    }

    /**
     * Boot safe Blade directives for strict mode.
     *
     * Design:
     * - Директивы возвращают чистый HTML/Blade, без PHP.
     * - Никаких вызовов сервисов/гейтов из шаблонов.
     * - Только синтаксический сахар для вывода.
     */
    public function boot(): void
    {
        Logger::log('PROVIDER.BOOT', static::class . ' boot');

        // Поделимся CSRF-токеном как переменной, чтобы {{ }} оставались строгими.
        try {
            /** @var ViewFactory $view */
            $view = $this->app->make('view');
            if (method_exists($view, 'share')) {
                $view->share('_csrf', csrf_token());
            }
        } catch (\Throwable $e) {
            // Ничего: формы будут без _csrf, VerifyCsrfToken их отклонит.
        }

        // Зарегистрируем безопасные директивы.
        try {
            /** @var BladeEngine $blade */
            $blade = $this->app->make('blade');

            // @csrf -> скрытое поле с ранее расшаренным значением
            $blade->addDirective('csrf', static function (?string $expr = null): string {
                return '<input type="hidden" name="_token" value="{{ $_csrf }}">';
            });

            // @method('PUT') -> скрытое поле _method. Разрешаем только буквы.
            $blade->addDirective('method', static function (?string $expr = null): string {
                $raw = (string)($expr ?? '');
                $raw = trim($raw);
                if (str_starts_with($raw, '(') && str_ends_with($raw, ')')) {
                    $raw = substr($raw, 1, -1);
                }
                $raw = trim($raw);
                if ((str_starts_with($raw, "'") && str_ends_with($raw, "'")) ||
                    (str_starts_with($raw, '"') && str_ends_with($raw, '"'))) {
                    $raw = substr($raw, 1, -1);
                }
                if (!preg_match('~^[A-Za-z]+$~', $raw)) {
                    $raw = 'POST';
                }
                $escaped = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
                return '<input type="hidden" name="_method" value="' . $escaped . '">';
            });
        } catch (\Throwable $e) {
            // Если Blade недоступен в этот момент, директивы пропустим.
        }
    }
}
