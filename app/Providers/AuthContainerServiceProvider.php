<?php // v0.4.1
/* app/Providers/AuthContainerServiceProvider.php
Назначение: прежний провайдер, завязанный на legacy-контейнер.
FIX: провайдер деактивирован: методы register()/boot() пустые. Вся актуальная
регистрация 'auth' выполняется в App\Providers\AuthServiceProvider.
*/

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;

final class AuthContainerServiceProvider extends ServiceProvider
{
    /**
     * Ничего не регистрируем — провайдер выведен из эксплуатации.
     *
     * @return void
     */
    public function register(): void
    {
        // no-op
    }

    /**
     * Никаких boot-действий — всё делает AuthServiceProvider.
     *
     * @return void
     */
    public function boot(): void
    {
        // no-op
    }
}
