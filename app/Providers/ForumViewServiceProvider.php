<?php // v0.4.1
/* app/Providers/ForumViewServiceProvider.php
Назначение: провайдер уровня приложения, регистрирующий view-композеры
для форумных и layout-шаблонов, как в Laravel (через фасад View).
FIX: заменена «мягкая» регистрация на каноничную: View::composer([...], Class::class);
добавлены паттерны 'forum.*' и 'layouts.*' одним вызовом.
*/
namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use Faravel\Support\Facades\View;
use App\Http\Composers\ForumBasePageComposer;

use App\Support\Logger;

final class ForumViewServiceProvider extends ServiceProvider
{
    /**
     * Регистрации контейнера не требуются.
     *
     * @return void
     */
    public function register(): void
    {
        // Debug: provider register
        Logger::log('PROVIDER.REGISTER', static::class . ' register');
        // no bindings here
    }

    /**
     * Точка входа: регистрируем композер «форум + лейауты».
     * Это Laravel-образный способ, чтобы любые forum.* и layouts.*
     * получали подготовленный $layout автоматически при создании View.
     *
     * @return void
     *
     * @example
     *  // где-то в boot других провайдеров
     *  // View::composer('admin.*', AdminComposer::class);
     */
    public function boot(): void
    {
        // Debug: provider boot
        Logger::log('PROVIDER.BOOT', static::class . ' boot');

        // Каноничная регистрация: один класс на несколько паттернов.
        View::composer(['forum.*', 'layouts.*'], ForumBasePageComposer::class);
    }
}
