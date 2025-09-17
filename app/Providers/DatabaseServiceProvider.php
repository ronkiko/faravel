<?php // v0.4.1
/* app/Providers/DatabaseServiceProvider.php
Purpose: Сервис‑провайдер для базы данных. Регистрирует экземпляр Database
         в контейнере приложения. Выполняет биндинг singleton 'db'.
FIX: Добавлено простое отладочное логирование [DATABASE.PROVIDER.REGISTER]
     через Logger::log(), записывающее «Loaded OK» в debug.log при включённом
     режиме debug.
*/

namespace App\Providers;

use Faravel\Database\Database;
use Faravel\Foundation\ServiceProvider;
use App\Support\Logger;

/**
 * Provider for database bindings. Registers Database instance in the container.
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register the Database singleton in the DI container.
     *
     * Preconditions: none.
     * Side effects: binds 'db' in the container.
     *
     * @return void
     */
    public function register(): void
    {
        // Log registration
        Logger::log('DATABASE.PROVIDER.REGISTER', 'Loaded OK');
        $this->app->singleton('db', function () {
            // Configuration will be obtained inside Database automatically
            return new Database();
        });
    }

    /**
     * Post-registration hook (unused here).
     *
     * @return void
     */
    public function boot(): void
    {
        // No post-registration logic
    }
}