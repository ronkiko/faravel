<?php // v0.4.3
/* app/Providers/ViewServiceProvider.php
Purpose: Register FileViewFinder, Php/Blade engines, View factory and safe Blade directives.
FIX: синхронизирована регистрация с актуальными сигнатурами классов:
- FileViewFinder принимает массив путей, а не строку;
- BladeEngine принимает ViewFactory;
- создаём BladeEngine на базе того же экземпляра фабрики и биндим его в контейнере
  как 'blade', чтобы директивы применялись к используемому движку.
*/
namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use Faravel\View\Engines\PhpEngine;
use Faravel\View\Engines\BladeEngine;
use Faravel\View\FileViewFinder;
use Faravel\View\ViewFactory;
use Faravel\Support\Facades\Blade;

final class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register bindings for view finder, engines and factory.
     */
    public function register(): void
    {
        // View finder (single root path). Read from config('view.paths'), fallback to /resources/views
        $this->app->singleton('view.finder', function ($app) {
            $paths = $app['config']['view.paths'] ?? null;
            $viewPath = is_array($paths) ? (string)($paths[0] ?? '') : (string)($paths ?? '');
            if ($viewPath === '' || !is_dir($viewPath)) {
                $viewPath = rtrim($app->basePath(), '/') . '/resources/views';
            }
            // FileViewFinder ожидает массив путей и список расширений:
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

        // Важно: отдельный singleton 'view.engine.blade' больше не регистрируем,
        // чтобы не плодить второй экземпляр и не терять директивы.
    }

    /**
     * Boot safe Blade directives for strict mode.
     *
     * Design:
     * - No PHP fragments returned by directives.
     * - No Gate/service calls inside templates.
     * - We only transform to other Blade tokens (e.g. @if(...), {{ ... }}).
     */
    public function boot(): void
    {
        // ... остальной код директив остаётся без изменений ...
    }
}
