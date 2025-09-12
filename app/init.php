<?php // v0.4.3
/* app/init.php
Назначение: ранний bootstrap слоя приложения. Регистрирует автозагрузчики PSR-4-подобной
         схемы для пространств имён App\ и Database\ (seeders/factories).
FIX: Добавлен автолоадер для пространства имён Database\ → каталога database/,
     чтобы классы сидеров (Database\Seeders\*) корректно подхватывались раннером.
*/

// ---- App\ autoload ----
spl_autoload_register(
    /**
     * PSR-4-like autoload for App\ classes.
     *
     * @param string $class Fully qualified class name.
     * Preconditions: $class starts with 'App\'.
     * Side effects: requires PHP files from app/.
     * @return void
     */
    function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }
        $baseDir = __DIR__ . '/';
        $relativeClass = substr($class, strlen('App\\')); // remove 'App\'
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);

// ---- Database\ autoload (seeders/factories) ----
spl_autoload_register(
    /**
     * PSR-4-like autoload for Database\* (e.g., Database\Seeders\…).
     * Mirrors Laravel's composer.json mapping: "Database\\": "database/".
     *
     * @param string $class Fully qualified class name.
     * Preconditions: $class starts with 'Database\'.
     * Side effects: requires PHP files from database/.
     * @return void
     */
    function (string $class): void {
        if (!str_starts_with($class, 'Database\\')) {
            return;
        }
        $projectRoot = dirname(__DIR__);        // …/forum
        $baseDir     = $projectRoot . '/database/';
        $relative    = substr($class, strlen('Database\\')); // strip prefix
        $file        = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);
