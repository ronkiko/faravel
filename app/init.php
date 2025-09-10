<?php // v0.4.2
/* app/init.php
Назначение: ранний bootstrap слоя App\: простой автозагрузчик классов приложения.
FIX: возвращён автолоадер пространства имён App\; удалены создание контейнера и
регистрация провайдеров (это делает bootstrap/app.php). Файл ничего не возвращает.
*/

spl_autoload_register(
    /**
     * Автозагрузка классов из пространства имён App\ по PSR-4-подобной схеме.
     *
     * @param string $class Полное имя класса.
     * @return void
     */
    function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }
        $baseDir = __DIR__ . '/';
        $relativeClass = str_replace('App\\', '', $class);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);
